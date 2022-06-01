<?php

namespace white\commerce\picqer\migrations;

use craft\db\Migration;

/**
 * m201118_144509_webhooks_table migration.
 */
class m201118_144509_webhooks_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
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
    }
}
