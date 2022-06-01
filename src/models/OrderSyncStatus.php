<?php


namespace white\commerce\picqer\models;

use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use DateTime;

class OrderSyncStatus extends Model
{
    public const STATUS_CONCEPT = 'concept';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_CONCEPT => "Concept",
        self::STATUS_PROCESSING => "Processing",
        self::STATUS_COMPLETED => "Completed",
        self::STATUS_CANCELLED => "Cancelled",
    ];
    
    /** @var integer */
    public int $id;

    /** @var integer */
    public int $orderId;

    /** @var integer|null */
    public ?int $picqerOrderId = null;

    /** @var boolean */
    public bool $pushed = false;

    /** @var boolean */
    public bool $stockAllocated = false;

    /** @var boolean */
    public bool $processed = false;

    /** @var string|null */
    public ?string $picqerOrderNumber;

    /** @var string|null */
    public ?string $publicStatusPage;
    
    public DateTime $dateCreated;
    public DateTime $dateUpdated;
    public ?DateTime $dateDeleted = null;
    public string $uid;

    /**
     * @var Order|null Order
     */
    private ?Order $_order = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['orderId'], 'required'],
        ];
    }

    /**
     * @return Order|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getOrder(): ?Order
    {
        if ($this->_order === null) {
            $this->_order = CommercePlugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     * @throws \Exception
     */
    public function setOrder(Order $order): void
    {
        if ($this->orderId != $order->id) {
            throw new \Exception("Cannot change order ID.");
        }
        
        $this->_order = $order;
        $this->orderId = $order->id;
    }
}
