<?php


namespace white\commerce\picqer\controllers;


use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\web\Controller;
use Picqer\Api\PicqerWebhook;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\models\OrderSyncStatus;
use white\commerce\picqer\models\Webhook;
use yii\helpers\VarDumper;
use yii\web\HttpException;

class WebhooksController extends Controller
{
    /**
     * @var \white\commerce\picqer\models\Settings
     */
    private $settings;

    /**
     * @var mixed|\white\commerce\picqer\services\Log
     */
    private $log;

    /**
     * @var \white\commerce\picqer\services\ProductSync
     */
    private $productSync;
    
    /**
     * @var mixed|\white\commerce\picqer\services\Webhooks
     */
    private $webhooks;

    
    protected $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;


    public function init()
    {
        parent::init();
        $this->enableCsrfValidation = false;
        
        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->log = CommercePicqerPlugin::getInstance()->log;
        $this->productSync = CommercePicqerPlugin::getInstance()->productSync;
        $this->webhooks = CommercePicqerPlugin::getInstance()->webhooks;
    }

    public function actionOnProductStockChanged()
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

    public function actionOnOrderStatusChanged()
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
            
            $order = Order::find()
                ->reference($data['reference'])
                ->anyStatus()
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
                    throw new \Exception("Could not update order status. " . json_encode($order->getFirstErrors()));
                } else {
                    $this->log->log("Order status changed to '{$order->orderStatusId}' for order '{$order->reference}'.");
                }
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
        }
        finally {
            $this->settings->pushOrders = $originalPushOrders;
        }

        return $this->asJson(['status' => 'OK']);
    }

    /**
     * @param string $type
     * @return PicqerWebhook|\yii\web\Response
     * @throws \Picqer\Api\WebhookException
     * @throws \Picqer\Api\WebhookSignatureMismatchException
     */
    protected function receiveWebhook($type)
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
