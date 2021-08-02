<?php


namespace white\commerce\picqer\console\controllers;

use craft\helpers\App;
use white\commerce\picqer\CommercePicqerPlugin;

class ImportProductStockController extends \yii\console\Controller
{
    /**
     * Limit processing to specified amount of entries.
     * @var integer|null
     */
    public $limit = null;

    /**
     * Skip the specified amount of entries before starting the processing.
     * @var integer|null
     */
    public $offset = null;

    /**
     * Enable debug mode: stop on the first error.
     * @var bool
     */
    public $debug = false;
    
    /**
     * @var \white\commerce\picqer\services\PicqerApi
     */
    private $picqerApi;
    
    /**
     * @var \white\commerce\picqer\models\Settings
     */
    private $settings;
    
    /**
     * @var \white\commerce\picqer\services\Log
     */
    private $log;
    
    /**
     * @var \white\commerce\picqer\services\ProductSync
     */
    private $productSync;

    public function init()
    {
        parent::init();
        
        $this->picqerApi = CommercePicqerPlugin::getInstance()->api;
        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->log = CommercePicqerPlugin::getInstance()->log;
        $this->productSync = CommercePicqerPlugin::getInstance()->productSync;
    }


    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'limit';
        $options[] = 'offset';
        $options[] = 'debug';

        return $options;
    }

    public function beforeAction($action)
    {
        App::maxPowerCaptain();

        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        if (!$this->settings->pullProductStock) {
            $this->stderr("Product stock synchronization is disabled. Please check your plugin settings." . PHP_EOL);
            return 1;
        }
        
        $this->log->log("Importing product stock from Picqer.");

        $i = 0;
        $count = 0;
        foreach ($this->picqerApi->getProducts() as $product) {
            $i++;
            if ($i <= $this->offset) {
                continue;
            }
            if ($this->limit !== null && ($i - $this->offset) > $this->limit) {
                break;
            }
            
            try {
                $this->processProduct($product);
                $count++;
            } catch (\Exception $e) {
                $this->log->error("Cound not process a product.", $e);
                
                if ($this->debug) {
                    throw $e;
                }
            }
        }
        
        $this->log->log("Product stock import finished. Total products processed: {$count}.");
        return 0;
    }
    
    protected function processProduct($productData)
    {
        $sku = $productData['productcode'];

        $totalFreeStock = 0;
        if (!empty($productData['stock'])) {
            foreach ($productData['stock'] as $item) {
                $totalFreeStock += $item['freestock'];
            }
        }

        $this->productSync->updateStock($sku, $totalFreeStock);
    }
}
