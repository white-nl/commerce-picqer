<?php


namespace white\commerce\picqer\controllers\admin;


use craft\helpers\UrlHelper;
use craft\web\Controller;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\models\Webhook;

class WebhooksController extends Controller
{
    /**
     * @var \white\commerce\picqer\models\Settings
     */
    private $settings;
    
    /**
     * @var mixed|\white\commerce\picqer\services\PicqerApi
     */
    private $picqerApi;
    
    /**
     * @var mixed|\white\commerce\picqer\services\Webhooks
     */
    private $webhooks;

    public function init()
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-picqer');

        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->picqerApi = CommercePicqerPlugin::getInstance()->api;
        $this->webhooks = CommercePicqerPlugin::getInstance()->webhooks;
    }

    public function actionGetHookStatus()
    {
        $type = $this->request->getRequiredParam('type');
        
        $settings = $this->webhooks->getWebhookByType($type);
        if (!$settings || empty($settings->picqerHookId)) {
            return $this->asJson([
                'status' => 'none',
                'statusText' => \Craft::t('commerce-picqer', 'Not registered'),
            ]);
        }

        $hookInfo = $this->picqerApi->getHook($settings->picqerHookId);
        if (!$hookInfo) {
            return $this->asJson([
                'status' => 'none',
                'statusText' => \Craft::t('commerce-picqer', 'Not registered'),
            ]);
        }

        if (!empty($hookInfo['active'])) {
            return $this->asJson([
                'status' => 'active',
                'statusText' => \Craft::t('commerce-picqer', 'Active'),
                'hookInfo' => $hookInfo,
            ]);
        }

        return $this->asJson([
            'status' => 'inactive',
            'statusText' => \Craft::t('commerce-picqer', 'Not active'),
            'hookInfo' => $hookInfo,
        ]);
    }

    public function actionRefresh()
    {
        $generalConfig = \Craft::$app->getConfig()->getGeneral();
        
        $type = $this->request->getRequiredBodyParam('type');

        $settings = $this->webhooks->getWebhookByType($type);
        if ($settings && !empty($settings->picqerHookId)) {
            $this->picqerApi->deleteHook($settings->picqerHookId);
            $this->webhooks->delete($settings);
        }

        $secret = md5(mt_rand());
        $event = null;
        $action = null;
        switch ($type) {
            case 'productStockSync':
                $event = 'products.free_stock_changed';
                $action = 'on-product-stock-changed';
                break;
            case 'orderStatusSync':
                $event = 'orders.status_changed';
                $action = 'on-order-status-changed';
                break;
        }
        
        $hookInfo = $this->picqerApi->createHook([
            'name' => 'Craft Commerce Picqer: ' . $type,
            'event' => $event,
            'address' => UrlHelper::siteUrl($generalConfig->actionTrigger . "/commerce-picqer/webhooks/{$action}/"),
            'secret' => $secret,
        ]);
        
        if (empty($hookInfo['idhook'])) {
            throw new \Exception("Could not create webhook: " . json_encode($hookInfo));
        }

        $settings = new Webhook([
            'type' => $type,
            'picqerHookId' => $hookInfo['idhook'],
            'secret' => $secret,
        ]);
        if (!$this->webhooks->saveWebhook($settings)) {
            throw new \Exception("Could not save webhook settings.");
        }

        return $this->asJson([
            'status' => 'active',
            'statusText' => \Craft::t('commerce-picqer', 'Active'),
            'hookInfo' => $hookInfo,
        ]);
    }

    public function actionRemove()
    {
        $type = $this->request->getRequiredBodyParam('type');

        $settings = $this->webhooks->getWebhookByType($type);
        if ($settings && !empty($settings->picqerHookId)) {
            $this->picqerApi->deleteHook($settings->picqerHookId);
            $this->webhooks->delete($settings);
        }

        return $this->asJson([
            'status' => 'inactive',
            'statusText' => \Craft::t('commerce-picqer', 'Not active'),
        ]);
    }
}
