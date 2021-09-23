<?php


namespace white\commerce\picqer\models;


use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;

class OrderSyncStatus extends Model
{
    const STATUS_CONCEPT = 'concept';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_CONCEPT => "Concept",
        self::STATUS_PROCESSING => "Processing",
        self::STATUS_COMPLETED => "Completed",
        self::STATUS_CANCELLED => "Cancelled",
    ];
    
    /** @var integer */
    public $id;

    /** @var integer */
    public $orderId;

    /** @var integer|null */
    public $picqerOrderId;

    /** @var boolean */
    public $pushed = false;

    /** @var boolean */
    public $stockAllocated = false;

    /** @var boolean */
    public $processed = false;

    /** @var string|null */
    public $picqerOrderNumber;

    /** @var string|null */
    public $publicStatusPage;
    
    public $dateCreated;
    public $dateUpdated;
    public $dateDeleted;
    public $uid;

    /**
     * @var Order Order
     */
    private $_order;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['orderId'], 'required'],
        ];
    }

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        if ($this->_order === null && $this->orderId !== null) {
            $this->_order = CommercePlugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     * @throws \Exception
     */
    public function setOrder(Order $order)
    {
        if ($this->orderId !== null && $this->orderId != $order->id) {
            throw new \Exception("Cannot change order ID.");
        }
        
        $this->_order = $order;
        $this->orderId = $order->id;
    }
}
