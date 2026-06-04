<?php


namespace white\commerce\picqer\services;

use craft\base\Component;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\elements\Variant;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\Plugin as CommercePlugin;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\models\Settings;

class ProductSync extends Component
{
    private ?Settings $settings = null;

    /**
     * @var Log
     */
    private Log $log;

    public function init(): void
    {
        parent::init();

        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->log = CommercePicqerPlugin::getInstance()->log;
    }

    /**
     * @param string $sku
     * @param int $stock
     * @return void
     * @throws \Throwable
     */
    public function updateStock(string $sku, int $stock): void
    {
        $variant = Variant::find()->sku($sku)->one();
        if (!$variant) {
            $this->log->trace("Variant '{$sku}' not found.");
            return;
        }

        if (!$variant->inventoryTracked || !$variant->inventoryItemId) {
            $this->log->trace("Variant '{$sku}' is not inventory-tracked.");
            return;
        }

        // Resolve the configured inventory location, falling back to the first available one.
        $inventoryLocationId = $this->settings->inventoryLocationId;
        if (!$inventoryLocationId) {
            $inventoryLocation = CommercePlugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first();
            if (!$inventoryLocation) {
                throw new \Exception("No inventory location found. Please configure an inventory location in the Picqer plugin settings.");
            }
            $inventoryLocationId = $inventoryLocation->id;
        }

        $inventoryService = CommercePlugin::getInstance()->getInventory();

        $currentLevel = $inventoryService->getInventoryLevel($variant->inventoryItemId, $inventoryLocationId);
        $currentStock = (int)($currentLevel?->availableTotal ?? 0);
        if ($currentStock === $stock) {
            $this->log->trace("Variant '{$sku}' inventory unchanged at '{$stock}' for location ID {$inventoryLocationId}; skipping update.");
            return;
        }

        $updateInventoryLevel = new UpdateInventoryLevel([
            'quantity' => $stock,
            'updateAction' => InventoryUpdateQuantityType::SET,
            'inventoryItemId' => $variant->inventoryItemId,
            'inventoryLocationId' => $inventoryLocationId,
            'type' => InventoryTransactionType::AVAILABLE->value,
            'note' => 'Updated from Picqer sync',
        ]);

        $updateInventoryLevels = UpdateInventoryLevelCollection::make();
        $updateInventoryLevels->push($updateInventoryLevel);

        $inventoryService->executeUpdateInventoryLevels($updateInventoryLevels);

        $this->log->trace("Variant '{$sku}' inventory set to '{$stock}' at location ID {$inventoryLocationId}.");
    }
}
