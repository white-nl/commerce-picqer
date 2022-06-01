<?php

namespace white\commerce\picqer\migrations;

use craft\db\Migration;

/**
 * m210526_103840_additional_ordersyncstatus_fields migration.
 */
class m210526_103840_additional_ordersyncstatus_fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->tableExists('{{%commercepicqer_ordersyncstatus}}')) {
            $this->addColumn('{{%commercepicqer_ordersyncstatus}}', 'picqerOrderNumber', $this->string());
            $this->addColumn('{{%commercepicqer_ordersyncstatus}}', 'publicStatusPage', $this->text());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        if ($this->db->tableExists('{{%commercepicqer_ordersyncstatus}}')) {
            $this->dropColumn('{{%commercepicqer_ordersyncstatus}}', 'picqerOrderNumber');
            $this->dropColumn('{{%commercepicqer_ordersyncstatus}}', 'publicStatusPage');
        }
    }
}
