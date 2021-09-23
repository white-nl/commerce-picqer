<?php


namespace white\commerce\picqer\controllers\admin;

use Craft;
use craft\commerce\Plugin as CommercePlugin;
use craft\web\Controller;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\errors\PicqerApiException;
use yii\web\NotFoundHttpException;

class OrdersController extends Controller
{
    public function init()
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-picqer');
    }

    public function actionPush()
    {
        $this->requirePermission('commerce-picqer-pushOrders');
        
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = CommercePlugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order || !$order->isCompleted) {
            throw new NotFoundHttpException();
        }

        $success = false;
        try {
            $status = CommercePicqerPlugin::getInstance()->orderSync->getOrderSyncStatus($order);
            $success = CommercePicqerPlugin::getInstance()->orderSync->pushOrder($status, true);
        } catch (\Exception $e) {
            CommercePicqerPlugin::getInstance()->log->error("Could not push the order to Picqer.", $e);
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce-picqer', "The order has been successfully pushed to Picqer."));
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce-picqer', "Could not push the order to Picqer. Please check the error logs for more details."));
        }

        return $this->redirectToPostedUrl();
    }

    public function actionAllocate()
    {
        $this->requirePermission('commerce-picqer-pushOrders');
        
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = CommercePlugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order || !$order->isCompleted) {
            throw new NotFoundHttpException();
        }

        $success = false;
        try {
            $status = CommercePicqerPlugin::getInstance()->orderSync->getOrderSyncStatus($order);
            $success = CommercePicqerPlugin::getInstance()->orderSync->allocateStockForOrder($status);
        } catch (\Exception $e) {
            CommercePicqerPlugin::getInstance()->log->error("Could not allocate the stock for the order in Picqer.", $e);
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce-picqer', "The stock has been successfully allocated."));
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce-picqer', "Could not allocate the stock for the order in Picqer. Please check the error logs for more details."));
        }

        return $this->redirectToPostedUrl();
    }

    public function actionProcess()
    {
        $this->requirePermission('commerce-picqer-pushOrders');
        
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = CommercePlugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order || !$order->isCompleted) {
            throw new NotFoundHttpException();
        }

        $success = false;
        try {
            $status = CommercePicqerPlugin::getInstance()->orderSync->getOrderSyncStatus($order);
            $success = CommercePicqerPlugin::getInstance()->orderSync->processOrder($status);
        } catch (\Exception $e) {
            CommercePicqerPlugin::getInstance()->log->error("Could not process the order in Picqer.", $e);
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce-picqer', "The order has been processed successfully."));
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce-picqer', "Could not process the order in Picqer. Please check the error logs for more details."));
        }

        return $this->redirectToPostedUrl();
    }

}
