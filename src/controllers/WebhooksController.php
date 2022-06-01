<?php


namespace white\commerce\picqer\controllers;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\web\Controller;
use Picqer\Api\PicqerWebhook;
use Picqer\Api\WebhookException;
use Picqer\Api\WebhookSignatureMismatchException;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\models\OrderSyncStatus;
use white\commerce\picqer\models\Settings;
use white\commerce\picqer\services\Log;
use white\commerce\picqer\services\ProductSync;
use white\commerce\picqer\services\Webhooks;
use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\Response;

class WebhooksController extends Controller
{
    private ?Settings $settings = null;

    /**
     * @var mixed|Log
     */
    private mixed $log;

    /**
     * @var ProductSync|null
     */
    private ?ProductSync $productSync = null;
    
    /**
     * @var mixed|Webhooks
     */
    private mixed $webhooks;

    
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;


    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        $this->enableCsrfValidation = false;
        
        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->log = CommercePicqerPlugin::getInstance()->log;
        $this->productSync = CommercePicqerPlugin::getInstance()->productSync;
        $this->webhooks = CommercePicqerPlugin::getInstance()->webhooks;
    }

    /**
     * @return Response
     */
    public function actionOnProductStockChanged(): Response
    {
        if (!$this->settings->pullProductStock) {
            return $this->asJson(['status' => 'IGNORED'])->setStatusCode(400);
        }
        
        try {
            $webhook = $this->receiveWebhook('productStockSync');
            
            $data = $webhook->getData();
            if (empty($data['productcode'])) {
                $this->log->trace("Webhook for a product without a productcode received. Ignoring.");
                return $this->asJson(['status' => 'OK']);
            }

            $sku = $data['productcode'];
            $totalFreeStock = 0;
            if (!empty($data['stock'])) {
                foreach ($data['stock'] as $item) {
                    $totalFreeStock += $item['freestock'];
                }
            }
            
            $this->log->log("Updating stock for product '$sku': {$totalFreeStock}");
            $this->productSync->updateStock($sku, $totalFreeStock);
        } catch (HttpException $e) {
            $this->log->error("Could not process webhook", $e);
            return $this->asJson(['status' => 'ERROR'])->setStatusCode($e->statusCode);
        } catch (\Exception $e) {
            $this->log->error("Could not process webhook", $e);
            return $this->asJson(['status' => 'ERROR'])->setStatusCode(500);
        }

        return $this->asJson(['status' => 'OK']);
    }

    /**
     * @return Response
     * @throws \Throwable
     */
    public function actionOnOrderStatusChanged(): Response
    {
        if (!$this->settings->pullOrderStatus) {
            return $this->asJson(['status' => 'IGNORED'])->setStatusCode(400);
        }
        
        $originalPushOrders = $this->settings->pushOrders;
        $this->settings->pushOrders = false;
        try {
            $webhook = $this->receiveWebhook('orderStatusSync');
            $data = $webhook->getData();
            
            if (empty($data['reference'])) {
                $this->log->trace("Webhook for an order without a reference received. Ignoring.");
                return $this->asJson(['status' => 'OK']);
            }
            
            if (empty($data['status'])) {
                throw new \Exception("Invalid webhook data received.");
            }

            /** @var Order|null $order */
            $order = Order::find()
                ->reference($data['reference'])
                ->status(null)
                ->one();
            if (!$order) {
                $this->log->trace("Order '{$data['reference']}' not found.");
                return $this->asJson(['status' => 'OK']);
            }
            
            $statusId = null;
            foreach ($this->settings->orderStatusMapping as $mapping) {
                if (!empty($mapping['craft'])) {
                    $orderStatus = $order->getOrderStatus();
                    if (!$orderStatus || $orderStatus->handle != $mapping['craft']) {
                        continue;
                    }
                }
                if ($mapping['picqer'] == $data['status']) {
                    $orderStatus = CommercePlugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle($mapping['changeTo']);
                    if (!$orderStatus) {
                        throw new \Exception("Order status '{$mapping['changeTo']}' not found in Craft.");
                    }
                    $statusId = $orderStatus->id;
                    break;
                }
            }
            
            if ($statusId !== null && $statusId != $order->orderStatusId) {
                $order->orderStatusId = $statusId;
                $order->message = \Craft::t('commerce-picqer',"[Picqer] Status updated via webhook ({status})",['status' => $data['status']]);
                if (!\Craft::$app->getElements()->saveElement($order)) {
                    throw new \Exception("Could not update order status. " . json_encode($order->getFirstErrors(), JSON_THROW_ON_ERROR));
                }

                $this->log->log("Order status changed to '{$order->orderStatusId}' for order '{$order->reference}'.");
            }

            if ($data['status'] == OrderSyncStatus::STATUS_COMPLETED ||
                $data['status'] == OrderSyncStatus::STATUS_PROCESSING) {
                $status = CommercePicqerPlugin::getInstance()->orderSync->getOrderSyncStatus($order);
                $status->stockAllocated = true;
                $status->processed = true;
                CommercePicqerPlugin::getInstance()->orderSync->saveOrderSyncStatus($status);
            }
        } catch (HttpException $e) {
            $this->log->error("Could not process webhook", $e);
            return $this->asJson(['status' => 'ERROR'])->setStatusCode($e->statusCode);
        } catch (\Exception $e) {
            $this->log->error("Could not process webhook", $e);
            return $this->asJson(['status' => 'ERROR'])->setStatusCode(500);
        } finally {
            $this->settings->pushOrders = $originalPushOrders;
        }

        return $this->asJson(['status' => 'OK']);
    }

    /**
     * @param string $type
     * @return PicqerWebhook|Response
     * @throws WebhookException
     * @throws WebhookSignatureMismatchException
     */
    protected function receiveWebhook(string $type): PicqerWebhook|Response
    {
        $settings = $this->webhooks->getWebhookByType($type);
        if (!$settings) {
            throw new \Exception("Webhook is not configured.");
        }

        $webhook = !empty($settings->secret) ? PicqerWebhook::retrieveWithSecret($settings->secret) : PicqerWebhook::retrieve();
        $this->log->trace("Picqer Webhook received: #{$webhook->getIdhook()}, {$webhook->getEvent()}({$webhook->getEventTriggeredAt()})");
        
        if ($webhook->getIdhook() != $settings->picqerHookId) {
            throw new \Exception("Invalid webhook ID: {$webhook->getIdhook()}");
        }
        
        return $webhook;
    }
}
