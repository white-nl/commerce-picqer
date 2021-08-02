<?php


namespace white\commerce\picqer\services;


use craft\base\Component;
use craft\commerce\elements\Variant;
use craft\commerce\records\Variant as VariantRecord;
use white\commerce\picqer\CommercePicqerPlugin;

class ProductSync extends Component
{
    /**
     * @var \white\commerce\picqer\models\Settings
     */
    private $settings;

    /**
     * @var \white\commerce\picqer\services\Log
     */
    private $log;

    public function init()
    {
        parent::init();

        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->log = CommercePicqerPlugin::getInstance()->log;
    }
    
    public function updateStock($sku, $stock)
    {
        if ($this->settings->fastStockUpdate) {
            $variantRecord = VariantRecord::findOne(['sku' => $sku]);
            if (!$variantRecord) {
                $this->log->trace("Variant '{$sku}' not found.");
                return;
            }

            if ($variantRecord->stock != $stock) {
                $variantRecord->stock = $stock;

                if (!$variantRecord->save(false, ['stock'])) {
                    throw new \Exception("Could not save variant stock.");
                }
                $this->log->trace("Variant '{$sku}' stock updated to '{$stock}'.");
            } else {
                $this->log->trace("Variant '{$sku}' stock remains unchanged: '{$stock}'");
            }
        }
        else {
            $variant = Variant::find()->sku($sku)->one();
            if (!$variant) {
                $this->log->trace("Variant '{$sku}' not found.");
                return;
            }

            if ($variant->stock != $stock) {
                $variant->stock = $stock;

                if (!\Craft::$app->getElements()->saveElement($variant)) {
                    throw new \Exception("Could not save variant stock. " . implode("\n", $variant->getFirstErrors()));
                }
                $this->log->trace("Variant '{$sku}' stock updated to '{$stock}'.");
            } else {
                $this->log->trace("Variant '{$sku}' stock remains unchanged: '{$stock}'");
            }
        }
    }
}
