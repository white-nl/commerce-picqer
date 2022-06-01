<?php


namespace white\commerce\picqer\controllers\admin;

use Craft;
use craft\web\Controller;
use white\commerce\picqer\CommercePicqerPlugin;
use yii\web\Response;

class SettingsController extends Controller
{
    public function init(): void
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-picqer');
    }

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $settings = CommercePicqerPlugin::getInstance()->getSettings();
        $settings->validate();

        /** @var Controller $controller */
        $controller = Craft::$app->controller;

        return $controller->renderTemplate('commerce-picqer/_settings', [
            'plugin' => CommercePicqerPlugin::getInstance(),
            'settings' => $settings,
            'allowAdminChanges' => Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }
}
