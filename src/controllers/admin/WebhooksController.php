<?php


namespace white\commerce\picqer\controllers\admin;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\errors\PicqerApiException;
use white\commerce\picqer\models\Webhook;
use white\commerce\picqer\services\PicqerApi;
use white\commerce\picqer\services\Webhooks;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class WebhooksController extends Controller
{
    /**
     * @var mixed|PicqerApi
     */
    private mixed $picqerApi;
    
    /**
     * @var mixed|Webhooks
     */
    private mixed $webhooks;

    /**
     * @return void
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-picqer');

        $this->picqerApi = CommercePicqerPlugin::getInstance()->api;
        $this->webhooks = CommercePicqerPlugin::getInstance()->webhooks;
    }

    /**
     * @return Response
     * @throws PicqerApiException
     * @throws BadRequestHttpException
     */
    public function actionGetHookStatus(): Response
    {
        $type = $this->request->getRequiredParam('type');
        
        $settings = $this->webhooks->getWebhookByType($type);
        if (!$settings || empty($settings->picqerHookId)) {
            return $this->asJson([
                'status' => 'none',
                'statusText' => Craft::t('commerce-picqer', 'Not registered'),
            ]);
        }

        $hookInfo = $this->picqerApi->getHook($settings->picqerHookId);
        if (!$hookInfo) {
            return $this->asJson([
                'status' => 'none',
                'statusText' => Craft::t('commerce-picqer', 'Not registered'),
            ]);
        }

        if (!empty($hookInfo['active'])) {
            return $this->asJson([
                'status' => 'active',
                'statusText' => Craft::t('commerce-picqer', 'Active'),
                'hookInfo' => $hookInfo,
            ]);
        }

        return $this->asJson([
            'status' => 'inactive',
            'statusText' => Craft::t('commerce-picqer', 'Not active'),
            'hookInfo' => $hookInfo,
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws PicqerApiException
     * @throws \JsonException
     * @throws \Exception
     */
    public function actionRefresh(): Response
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        
        $type = $this->request->getRequiredBodyParam('type');

        $settings = $this->webhooks->getWebhookByType($type);
        if ($settings && !empty($settings->picqerHookId)) {
            $this->picqerApi->deleteHook($settings->picqerHookId);
            $this->webhooks->delete($settings);
        }

        $secret = md5((string)random_int(0, mt_getrandmax()));
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
            'statusText' => Craft::t('commerce-picqer', 'Active'),
            'hookInfo' => $hookInfo,
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws PicqerApiException
     */
    public function actionRemove(): Response
    {
        $type = $this->request->getRequiredBodyParam('type');

        $settings = $this->webhooks->getWebhookByType($type);
        if ($settings && !empty($settings->picqerHookId)) {
            $this->picqerApi->deleteHook($settings->picqerHookId);
            $this->webhooks->delete($settings);
        }

        return $this->asJson([
            'status' => 'inactive',
            'statusText' => Craft::t('commerce-picqer', 'Not active'),
        ]);
    }
}
