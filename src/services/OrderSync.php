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

class OrderSync extends Component
{
    /** @var Settings */
    private $settings;
    
    /** @var PicqerApi */
    private $picqerApi;
    
    /** @var Log */
    private $log;
    
    public function init()
    {
        parent::init();

        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->picqerApi = CommercePicqerPlugin::getInstance()->api;
        $this->log = CommercePicqerPlugin::getInstance()->log;
    }

    public function registerEventListeners()
    {
        Event::on(
            Order::class,
            Order::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                if (!$this->settings->pushOrders) {
                    return;
                }

                if ($event->sender->propagating) {
                    return;
                }
                
                $this->pushOrderStatus($event->sender);
            }
        );
        
    }
    
    public function pushOrderStatus(Order $order)
    {
        $status = $this->getOrderSyncStatus($order);

        try {
            $orderStatusToPush = is_array($this->settings->orderStatusToPush) ? $this->settings->orderStatusToPush : [];
            $orderStatusToAllocate = is_array($this->settings->orderStatusToAllocate) ? $this->settings->orderStatusToAllocate : [];
            $orderStatusToProcess = is_array($this->settings->orderStatusToProcess) ? $this->settings->orderStatusToProcess : [];
            
            $orderStatus = $order->getOrderStatus();
            if (!$orderStatus) {
                return;
            }
            
            if (in_array($orderStatus->handle, $orderStatusToPush)) {
                if (!$status->pushed) {
                    
                    try {
                        $picqerData = $this->picqerApi->pushOrder($order);
                    } catch (PicqerApiException $e) {
                        if ($e->getPicqerErrorCode() == 24 && $this->settings->createMissingProducts) {
                            $purchasables = [];
                            foreach ($order->getLineItems() as $lineItem) {
                                if (!array_keys($purchasables, $lineItem->getSku())) {
                                    $purchasables[$lineItem->getSku()] = $lineItem->getPurchasable();
                                }
                            }

                            $this->picqerApi->createMissingProducts($purchasables);
                            $picqerData = $this->picqerApi->pushOrder($order);
                            
                        } else {
                            throw $e;
                        }
                    }
                    
                    $status->picqerOrderId = $picqerData['idorder'];
                    $status->picqerOrderNumber = $picqerData['orderid'];
                    $status->publicStatusPage = $picqerData['public_status_page'];
                    $status->pushed = true;
                    $this->saveOrderSyncStatus($status);
                    $this->log->log("Order #{$order->number} pushed to Picqer, PicqerOrderId={$status->picqerOrderId}.");
                }
            }

            if (in_array($orderStatus->handle, $orderStatusToAllocate) && !in_array($orderStatus->handle, $orderStatusToProcess)) {
                if ($status->pushed && !$status->stockAllocated) {
                    $this->picqerApi->allocateStockForOrder($status->picqerOrderId);
                    $status->stockAllocated = true;
                    $this->saveOrderSyncStatus($status);
                    $this->log->log("Picqer stock allocated for order #{$order->number}, PicqerOrderId={$status->picqerOrderId}.");
                }
            }

            if (in_array($orderStatus->handle, $orderStatusToProcess)) {
                if ($status->pushed && !$status->processed) {
                    $this->picqerApi->processOrder($status->picqerOrderId);
                    $status->stockAllocated = true;
                    $status->processed = true;
                    $this->saveOrderSyncStatus($status);
                    $this->log->log("Order #{$order->number} processed in Picqer, PicqerOrderId={$status->picqerOrderId}.");
                }
            }
        } catch (\Exception $e) {
            $this->log->error("Picqer order synchronization failed.", $e);
        }
    }
    
    public function getOrderSyncStatus(Order $order)
    {
        $record = OrderSyncStatusRecord::findOne([
            'orderId' => $order->id,
        ]);
        if (!$record) {
            $record = new OrderSyncStatusRecord([
                'orderId' => $order->id,
            ]);
        }
        
        return new OrderSyncStatus($record);
    }

    public function saveOrderSyncStatus(OrderSyncStatus $model)
    {
        $record = OrderSyncStatusRecord::findOne([
            'id' => $model->id,
        ]);
        if (!$record) {
            $record = new OrderSyncStatusRecord([
                'orderId' => $model->orderId,
            ]);
        }
        
        $record->picqerOrderId = $model->picqerOrderId;
        $record->pushed = (bool)$model->pushed;
        $record->stockAllocated = (bool)$model->stockAllocated;
        $record->processed = (bool)$model->processed;
        $record->picqerOrderNumber = $model->picqerOrderNumber;
        $record->publicStatusPage = $model->publicStatusPage;
        $record->dateCreated = $model->dateCreated;
        $record->dateUpdated = $model->dateUpdated;
        $record->dateDeleted = $model->dateDeleted;

        $record->save();
        $model->id = $record->id;
        $model->dateCreated = $record->dateCreated;

        return true;
    }
}
