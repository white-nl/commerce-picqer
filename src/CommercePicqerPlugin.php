<?php

namespace white\commerce\picqer;

use Craft;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UrlManager;
use white\commerce\picqer\models\Settings;
use white\commerce\picqer\services\Log;
use white\commerce\picqer\services\OrderSync;
use white\commerce\picqer\services\PicqerApi;
use white\commerce\picqer\services\ProductSync;
use white\commerce\picqer\services\Webhooks;
use yii\base\Event;

/**
 * @property PicqerApi $api
 * @property Log $log
 * @property OrderSync $orderSync
 * @property ProductSync $productSync
 * @property Webhooks $webhooks
 * 
 * @method Settings getSettings()
 */
class CommercePicqerPlugin extends Plugin
{
    public $schemaVersion = '1.0.3';
    
    public function init()
    {
        parent::init();

        $this->registerNameOverride();
        $this->registerServices();
        $this->registerEventListeners();
        $this->registerCpUrls();
    }

    protected function registerNameOverride()
    {
        $name = $this->getSettings()->pluginNameOverride;
        if (empty($name)) {
            $name = Craft::t('commerce-picqer', "Picqer");
        }

        $this->name = $name;
    }

    protected function registerServices()
    {
        $this->setComponents([
            'api' => PicqerApi::class,
            'log' => Log::class,
            'orderSync' => OrderSync::class,
            'productSync' => ProductSync::class,
            'webhooks' => Webhooks::class,
        ]);
    }

    protected function registerEventListeners()
    {
        $this->orderSync->registerEventListeners();

        Craft::$app->getView()->hook('cp.commerce.order.edit.details', function(array &$context) { // Commerce 3.2.0
            /** @var Order $order */
            $order = $context['order'];
            $status = $this->orderSync->getOrderSyncStatus($order);

            return Craft::$app->getView()->renderTemplate('commerce-picqer/_order-details-panel', [
                'plugin' => $this,
                'order' => $order,
                'status' => $status,
            ]);
        });
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce-picqer/settings'));
    }

    protected function registerCpUrls()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['commerce-picqer'] = 'commerce-picqer/admin/settings';
                $event->rules['commerce-picqer/settings'] = 'commerce-picqer/admin/settings';
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem()
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'settings' => [
                'label' => Craft::t('commerce-picqer', 'Settings'),
                'url' => 'commerce-picqer/settings'
            ],
        ];
        return $item;
    }
}