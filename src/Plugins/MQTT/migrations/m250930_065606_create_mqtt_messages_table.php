<?php

use Yew\Framework\Db\Migration;

/**
 * Handles the creation of table `{{%mqtt_message}}`.
 */
class m250930_065606_create_mqtt_message_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%mqtt_message}}', [
            'id' => $this->bigPrimaryKey()->comment('primary key'),
            'direction' => $this->smallInteger()->notNull()->defaultValue(0)->comment('0: up, 1: down'),
            'client_id' => $this->string(128)->notNull()->comment('client id'),
            'topic' => $this->string(255)->notNull()->comment('topic'),
            'payload' => $this->text()->notNull()->comment('payload'),
            'qos' => $this->smallInteger()->notNull()->defaultValue(0)->comment('qos'),
            'retain' => $this->smallInteger()->notNull()->defaultValue(0)->comment('retain'),
            'published_time' => $this->dateTime(6)->notNull()->comment('published time'),
            'from_client_id' => $this->string(128)->notNull()->comment('from client id'),
            'created_at' => $this->dateTime(6)->null()->comment('created at'),
            'updated_at' => $this->dateTime(6)->null()->comment('updated at'),
        ]);

        $this->createIndex('client_id', '{{%mqtt_message}}', 'client_id');
        $this->createIndex('topic', '{{%mqtt_message}}', 'topic');
        $this->createIndex('published_time', '{{%mqtt_message}}', 'published_time');
        $this->createIndex('from_client_id', '{{%mqtt_message}}', 'from_client_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%mqtt_message}}');
    }
}
