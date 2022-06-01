<?php


namespace white\commerce\picqer\services;

use craft\base\Component;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\events\ModelEvent;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\errors\PicqerApiException;
use white\commerce\picqer\models\OrderSyncStatus;
use white\commerce\picqer\models\Settings;
use white\commerce\picqer\records\OrderSyncStatus as OrderSyncStatusRecord;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

class OrderSync extends Component
{
    private ?Settings $settings = null;
    
    private ?PicqerApi $picqerApi = null;
    
    private ?Log $log = null;
    
    public function init(): void
    {
        parent::init();

        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->picqerApi = CommercePicqerPlugin::getInstance()->api;
        $this->log = CommercePicqerPlugin::getInstance()->log;
    }

    public function registerEventListeners(): void
    {
        Event::on(
            Order::class,
            Element::EVENT_AFTER_SAVE,
            function(ModelEvent $event): void {
                /** @var Order $order */
                $order = $event->sender;
                if (!$this->settings->pushOrders) {
                    return;
                }

                if ($order->propagating) {
                    return;
                }

                $this->syncOrder($order);
            }
        );
    }

    /**
     * @param Order $order
     * @return void
     */
    public function syncOrder(Order $order): void
    {
        $status = $this->getOrderSyncStatus($order);

        try {
            $orderStatusToPush = $this->settings->orderStatusToPush;
            $orderStatusToAllocate = $this->settings->orderStatusToAllocate;
            $orderStatusToProcess = $this->settings->orderStatusToProcess;
            $orderStatus = $order->getOrderStatus();
            if (!$orderStatus) {
                return;
            }
            
            if (in_array($orderStatus->handle, $orderStatusToPush, true)) {
                $this->pushOrder($status);
            }
            if (in_array($orderStatus->handle, $orderStatusToAllocate, true) && !in_array($orderStatus->handle, $orderStatusToProcess, true)) {
                $this->allocateStockForOrder($status);
            }
            if (in_array($orderStatus->handle, $orderStatusToProcess, true)) {
                $this->processOrder($status);
            }
        } catch (\Exception $e) {
            $this->log->error("Picqer order synchronization failed.", $e);
        }
    }

    /**
     * @param OrderSyncStatus $status
     * @param bool $force
     * @return bool
     * @throws PicqerApiException
     * @throws InvalidConfigException
     */
    public function pushOrder(OrderSyncStatus $status, bool $force = false): bool
    {
        $order = $status->getOrder();
        if ($order === null) {
            throw new \Exception("OrderSyncStatus::order is empty.");
        }
        
        if ($status->pushed && !$force) {
            return false;
        }

        if (empty($status->picqerOrderId)) {
            $picqerData = $this->picqerApi->pushOrder($order, $this->settings->createMissingProducts);
            $status->picqerOrderId = $picqerData['idorder'];
            $status->picqerOrderNumber = $picqerData['orderid'];
            $status->publicStatusPage = $picqerData['public_status_page'];
        } else {
            $this->picqerApi->updateOrder($status->picqerOrderId, $order, $this->settings->createMissingProducts);
        }
        
        $status->pushed = true;
        $this->saveOrderSyncStatus($status);
        $this->log->log("Order #{$order->number} pushed to Picqer, PicqerOrderId={$status->picqerOrderId}.");
        
        return true;
    }

    /**
     * @param OrderSyncStatus $status
     * @return bool
     * @throws InvalidConfigException
     * @throws PicqerApiException
     */
    public function allocateStockForOrder(OrderSyncStatus $status): bool
    {
        $order = $status->getOrder();
        if ($order === null) {
            throw new \Exception("OrderSyncStatus::order is empty.");
        }

        if (!$status->pushed || $status->stockAllocated) {
            return false;
        }

        try {
            $this->picqerApi->allocateStockForOrder($status->picqerOrderId);
        } catch (PicqerApiException $e) {
            if ($e->getPicqerErrorCode() != PicqerApiException::ORDER_ALREADY_CLOSED) {
                throw $e;
            }
        }
        
        $status->stockAllocated = true;
        $this->saveOrderSyncStatus($status);
        $this->log->log("Picqer stock allocated for order #{$order->number}, PicqerOrderId={$status->picqerOrderId}.");
        
        return true;
    }

    /**
     * @param OrderSyncStatus $status
     * @return bool
     * @throws InvalidConfigException
     * @throws PicqerApiException
     */
    public function processOrder(OrderSyncStatus $status): bool
    {
        $order = $status->getOrder();
        if ($order === null) {
            throw new \Exception("OrderSyncStatus::order is empty.");
        }

        if (!$status->pushed || $status->processed) {
            return false;
        }

        try {
            $this->picqerApi->processOrder($status->picqerOrderId);
        } catch (PicqerApiException $e) {
            if ($e->getPicqerErrorCode() != PicqerApiException::ORDER_ALREADY_CLOSED) {
                throw $e;
            }
        }
        
        $status->stockAllocated = true;
        $status->processed = true;
        $this->saveOrderSyncStatus($status);
        $this->log->log("Order #{$order->number} processed in Picqer, PicqerOrderId={$status->picqerOrderId}.");
        
        return true;
    }

    /**
     * @param Order $order
     * @return OrderSyncStatus
     * @throws \Exception
     */
    public function getOrderSyncStatus(Order $order): OrderSyncStatus
    {
        $record = OrderSyncStatusRecord::findOne([
            'orderId' => $order->id,
        ]);
        if (!$record) {
            $record = new OrderSyncStatusRecord([
                'orderId' => $order->id,
            ]);
        }
        
        $status = new OrderSyncStatus($record->toArray());
        $status->setOrder($order);

        return $status;
    }

    /**
     * @param OrderSyncStatus $model
     * @return bool
     */
    public function saveOrderSyncStatus(OrderSyncStatus $model): bool
    {
        if (isset($model->id)) {
            $record = OrderSyncStatusRecord::findOne([
                'id' => $model->id,
            ]);
            if (!$record instanceof OrderSyncStatusRecord) {
                throw new InvalidArgumentException('No order sync status exists with the ID "' . $model->id . '"');
            }
        } else {
            $record = new OrderSyncStatusRecord([
                'orderId' => $model->orderId,
            ]);
        }
        
        $record->picqerOrderId = $model->picqerOrderId;
        $record->pushed = $model->pushed;
        $record->stockAllocated = $model->stockAllocated;
        $record->processed = $model->processed;
        $record->picqerOrderNumber = $model->picqerOrderNumber;
        $record->publicStatusPage = $model->publicStatusPage;
        $record->dateDeleted = $model->dateDeleted ?? null;

        $record->save();
        $model->id = $record->getAttribute('id');

        return true;
    }
}
