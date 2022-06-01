<?php


namespace white\commerce\picqer\records;

use craft\db\ActiveRecord;

/**
 *
 * @property int|null $picqerOrderId
 * @property bool $pushed
 * @property bool $stockAllocated
 * @property bool $processed
 * @property string|null $picqerOrderNumber
 * @property string|null $publicStatusPage
 * @property \DateTime|null $dateDeleted
 */
class OrderSyncStatus extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commercepicqer_ordersyncstatus}}';
    }
}
