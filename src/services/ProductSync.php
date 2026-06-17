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

    private int $maxBatchSize = 100;

    private ?int $inventoryLocationId = null;

    private UpdateInventoryLevelCollection $pendingInventoryUpdates;

    /**
     * @var Log
     */
    private Log $log;

    public function init(): void
    {
        parent::init();

        $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        $this->log = CommercePicqerPlugin::getInstance()->log;
        $this->maxBatchSize = max(1, (int)$this->settings->maxBatchSize);
        $this->pendingInventoryUpdates = UpdateInventoryLevelCollection::make();
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

        $inventoryLocationId = $this->resolveInventoryLocationId();

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

        $this->pendingInventoryUpdates->push($updateInventoryLevel);
        $this->log->trace("Variant '{$sku}' inventory queued to '{$stock}' at location ID {$inventoryLocationId}.");

        if ($this->pendingInventoryUpdates->count() >= $this->maxBatchSize) {
            $this->flushStockUpdates();
        }
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function flushStockUpdates(): void
    {
        $count = $this->pendingInventoryUpdates->count();
        if ($count === 0) {
            return;
        }

        CommercePlugin::getInstance()->getInventory()->executeUpdateInventoryLevels($this->pendingInventoryUpdates);
        $this->pendingInventoryUpdates = UpdateInventoryLevelCollection::make();
        $this->log->trace("{$count} inventory updates executed in batch.");
    }

    /**
     * @return int
     * @throws \Exception
     */
    private function resolveInventoryLocationId(): int
    {
        if ($this->inventoryLocationId !== null) {
            return $this->inventoryLocationId;
        }

        $inventoryLocationId = (int)$this->settings->inventoryLocationId;
        if (!$inventoryLocationId) {
            $inventoryLocation = CommercePlugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first();
            if (!$inventoryLocation) {
                throw new \Exception("No inventory location found. Please configure an inventory location in the Picqer plugin settings.");
            }
            $inventoryLocationId = $inventoryLocation->id;
        }

        $this->inventoryLocationId = $inventoryLocationId;

        return $this->inventoryLocationId;
    }
}
