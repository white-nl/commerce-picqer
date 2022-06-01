<?php

namespace white\commerce\picqer;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use white\commerce\picqer\models\Settings;
use white\commerce\picqer\services\Log;
use white\commerce\picqer\services\OrderSync;
use white\commerce\picqer\services\PicqerApi;
use white\commerce\picqer\services\ProductSync;
use white\commerce\picqer\services\Webhooks;
use yii\base\Event;
use yii\console\Response;

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
    public string $schemaVersion = '1.0.3';
    
    public function init(): void
    {
        parent::init();

        $this->registerNameOverride();
        $this->registerServices();
        $this->registerEventListeners();
        $this->registerCpUrls();
        $this->registerPermissions();
    }

    protected function registerNameOverride(): void
    {
        $name = $this->getSettings()->pluginNameOverride;
        if (empty($name)) {
            $name = Craft::t('commerce-picqer', "Picqer");
        }

        $this->name = $name;
    }

    protected function registerServices(): void
    {
        $this->setComponents([
            'api' => PicqerApi::class,
            'log' => Log::class,
            'orderSync' => OrderSync::class,
            'productSync' => ProductSync::class,
            'webhooks' => Webhooks::class,
        ]);
    }

    protected function registerEventListeners(): void
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

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): Response|\craft\web\Response
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce-picqer/settings'));
    }

    protected function registerCpUrls(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event): void {
                $event->rules['commerce-picqer'] = 'commerce-picqer/admin/settings';
                $event->rules['commerce-picqer/settings'] = 'commerce-picqer/admin/settings';
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'settings' => [
                'label' => Craft::t('commerce-picqer', 'Settings'),
                'url' => 'commerce-picqer/settings',
            ],
        ];
        return $item;
    }

    protected function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event): void {
                $event->permissions[] = [
                    'heading' => Craft::t('commerce-picqer', 'Picqer'),
                    'permissions' => [
                        'commerce-picqer-pushOrders' => [
                            'label' => Craft::t('commerce-picqer', 'Manually push orders to Picqer'),
                        ],
                    ],
                ];
            }
        );
    }
}
