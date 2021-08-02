<?php


namespace white\commerce\picqer\controllers\admin;

use Craft;
use craft\web\Controller;
use white\commerce\picqer\CommercePicqerPlugin;

class SettingsController extends Controller
{
    public function init()
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-picqer');
    }

    public function actionIndex()
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
