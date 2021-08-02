<?php

namespace white\commerce\picqer\migrations;

use Craft;
use craft\db\Migration;

class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->tableExists('{{%commercepicqer_ordersyncstatus}}')) {
            $this->createTable('{{%commercepicqer_ordersyncstatus}}', [
                'id' => $this->bigPrimaryKey(),
                'orderId' => $this->integer()->notNull(),
                'picqerOrderId' => $this->bigInteger(),
                'pushed' => $this->boolean()->notNull(),
                'stockAllocated' => $this->boolean()->notNull(),
                'processed' => $this->boolean()->notNull(),
                'picqerOrderNumber' => $this->string(),
                'publicStatusPage' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'dateDeleted' => $this->dateTime()->null(),
                'uid' => $this->uid(),
            ]);
            
            $this->addForeignKey(
                $this->db->getForeignKeyName('{{%commercepicqer_ordersyncstatus}}', 'orderId'),
                '{{%commercepicqer_ordersyncstatus}}', 'orderId', '{{%commerce_orders}}', 'id', 'CASCADE', null);
            
            $this->createIndex(null, '{{%commercepicqer_ordersyncstatus}}', ['orderId'], true);
        }
        
        if (!$this->db->tableExists('{{%commercepicqer_webhooks}}')) {
            $this->createTable('{{%commercepicqer_webhooks}}', [
                'id' => $this->bigPrimaryKey(),
                'type' => $this->string(32)->notNull(),
                'picqerHookId' => $this->bigInteger()->notNull(),
                'secret' => $this->string()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            
            $this->createIndex(null, '{{%commercepicqer_webhooks}}', ['type'], true);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%commercepicqer_webhooks}}');
        $this->dropTableIfExists('{{%commercepicqer_ordersyncstatus}}');
    }
}
