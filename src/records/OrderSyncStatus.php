<?php


namespace white\commerce\picqer\records;


use craft\db\ActiveRecord;

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
