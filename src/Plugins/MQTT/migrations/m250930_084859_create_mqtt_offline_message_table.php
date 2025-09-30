<?php

use Yew\Framework\Db\Migration;

/**
 * Handles the creation of table `{{%mqtt_offline_message}}`.
 */
class m250930_084859_create_mqtt_offline_message_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%mqtt_offline_message}}', [
            'id' => $this->primaryKey()->comment('primary key'),
            'client_id' => $this->string(128)->notNull()->comment('client id'),
            'topic' => $this->string(255)->notNull()->comment('topic'),
            'qos' => $this->smallInteger()->notNull()->defaultValue(0)->comment('qos'),
            'payload' => $this->binary()->notNull()->comment('payload'),
            'delivered' => $this->smallInteger()->notNull()->defaultValue(0)->comment('delivered. 0: not delivered, 1: delivered'),
            'delivered_at' => $this->dateTime(6)->null()->comment('delivered at'),
            'created_at' => $this->dateTime(6)->null()->comment('created at'),
            'updated_at' => $this->dateTime(6)->null()->comment('updated at'),
        ]);

        $this->createIndex('client_id', '{{%mqtt_offline_message}}', 'client_id');
        $this->createIndex('topic', '{{%mqtt_offline_message}}', 'topic');
        $this->createIndex('delivered', '{{%mqtt_offline_message}}', 'delivered');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%mqtt_offline_message}}');
    }
}
