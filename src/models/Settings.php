<?php


namespace white\commerce\picqer\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as CommercePlugin;

class Settings extends Model
{
    public $apiDomain;
    
    public $apiKey;

    public $pushOrders = false;
    
    public $orderStatusToPush = [];
    
    public $orderStatusToAllocate = [];
    
    public $orderStatusToProcess = [];
    
    public $pushPrices = false;
    
    public $createMissingProducts = false;
    
    public $pullProductStock = false;
    
    public $pullOrderStatus = false;

    /**
     * @var array
     */
    public $orderStatusMapping = [];

    public $fastStockUpdate = false;

    public $pluginNameOverride;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['pluginNameOverride', 'default', 'value' => Craft::t('commerce-picqer', "Picqer")],
            ['orderStatusMapping', 'default', 'value' => []],
            [['apiDomain', 'apiKey'], 'required'],
        ];
    }

    public function getOrderStatusOptions($optional = null)
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
     *
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

    public function getApiDomain()
    {
        return \Craft::parseEnv($this->apiDomain);
    }

    public function getApiKey()
    {
        return \Craft::parseEnv($this->apiKey);
    }
}
