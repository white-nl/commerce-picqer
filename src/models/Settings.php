<?php


namespace white\commerce\picqer\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as CommercePlugin;
use craft\helpers\App;
use yii\base\InvalidConfigException;

/**
 *
 * @property-read array $picqerStatuses
 */
class Settings extends Model
{
    public string $apiDomain;
    
    public string $apiKey;

    public bool $pushOrders = false;
    
    public array $orderStatusToPush = [];
    
    public array $orderStatusToAllocate = [];
    
    public array $orderStatusToProcess = [];
    
    public bool $pushPrices = false;
    
    public bool $createMissingProducts = false;
    
    public bool $pullProductStock = false;
    
    public bool $pullOrderStatus = false;

    public array $orderStatusMapping = [];

    public bool $fastStockUpdate = false;

    public string $pluginNameOverride;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['pluginNameOverride', 'default', 'value' => Craft::t('commerce-picqer', "Picqer")],
            ['orderStatusMapping', 'default', 'value' => []],
            [['apiDomain', 'apiKey'], 'required'],
        ];
    }

    /**
     * @param string|null $optional
     * @return array
     * @throws InvalidConfigException
     */
    public function getOrderStatusOptions(?string $optional = null): array
    {
        $statuses = CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        $options = [];
        
        if ($optional !== null) {
            $options[] = ['value' => null, 'label' => $optional];
        }
        
        foreach ($statuses as $status) {
            $options[] = ['value' => $status->handle, 'label' => $status->displayName];
        }
        
        return $options;
    }

    /**
     * Get all Picqer statuses
     * @return array
     */
    public function getPicqerStatuses(): array
    {
        $options = [];
        foreach (OrderSyncStatus::STATUSES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return bool|string|null
     */
    public function getApiDomain(): bool|string|null
    {
        return App::parseEnv($this->apiDomain);
    }

    /**
     * @return bool|string|null
     */
    public function getApiKey(): bool|string|null
    {
        return App::parseEnv($this->apiKey);
    }
}
