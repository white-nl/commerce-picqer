<?php


namespace white\commerce\picqer\console\controllers;

use craft\helpers\App;
use Picqer\Api\Exception;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\models\Settings;
use white\commerce\picqer\services\Log;
use white\commerce\picqer\services\PicqerApi;
use white\commerce\picqer\services\ProductSync;

class ImportProductStockController extends \yii\console\Controller
{
    /**
     * Limit processing to specified amount of entries.
     * @var integer|null
     */
    public ?int $limit = null;

    /**
     * Skip the specified amount of entries before starting the processing.
     * @var integer|null
     */
    public ?int $offset = null;

    /**
     * Enable debug mode: stop on the first error.
     * @var bool
     */
    public bool $debug = false;
    
    private ?PicqerApi $picqerApi = null;
    
    private ?Settings $settings = null;
    
    private ?Log $log = null;
    
    private ?ProductSync $productSync = null;

    public function init(): void
    {
        parent::init();
        
        $this->picqerApi = CommercePicqerPlugin::getInstance()->api;
        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->log = CommercePicqerPlugin::getInstance()->log;
        $this->productSync = CommercePicqerPlugin::getInstance()->productSync;
    }


    /**
     * @param mixed $actionID
     * @return array|string[]
     */
    public function options(mixed $actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'limit';
        $options[] = 'offset';
        $options[] = 'debug';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        App::maxPowerCaptain();

        return parent::beforeAction($action);
    }

    /**
     * @return int
     * @throws Exception
     */
    public function actionIndex(): int
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

    /**
     * @param array $productData
     * @return void
     * @throws \Exception
     */
    protected function processProduct(array $productData): void
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
