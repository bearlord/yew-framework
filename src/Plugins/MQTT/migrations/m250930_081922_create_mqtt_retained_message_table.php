<?php

use Yew\Framework\Db\Migration;

/**
 * Handles the creation of table `{{%mqtt_retained_message}}`.
 */
class m250930_081922_create_mqtt_retained_message_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%mqtt_retained_message}}', [
            'id' => $this->primaryKey()->comment('primary key'),
            'topic' => $this->string(255)->notNull()->comment('topic'),
            'qos' => $this->smallInteger()->notNull()->defaultValue(0)->comment('qos'),
            'retain' => $this->smallInteger()->notNull()->defaultValue(0)->comment('retain'),
            'payload' => $this->binary()->notNull()->comment('payload'),
            'created_at' => $this->dateTime(6)->null()->comment('created at'),
            'updated_at' => $this->dateTime(6)->null()->comment('updated at'),
        ]);

        $this->createIndex('client_id', '{{%mqtt_retained_message}}', 'client_id');
        $this->createIndex('topic', '{{%mqtt_retained_message}}', 'topic');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%mqtt_retained_message}}');
    }
}
