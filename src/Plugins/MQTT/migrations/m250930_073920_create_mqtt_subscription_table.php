<?php

use Yew\Framework\Db\Migration;

/**
 * Handles the creation of table `{{%mqtt_subscription}}`.
 */
class m250930_073920_create_mqtt_subscription_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%mqtt_subscription}}', [
            'id' => $this->primaryKey()->comment('primary key'),
            'client_id' => $this->string(128)->notNull()->comment('client id'),
            'topic' => $this->string(255)->notNull()->comment('topic'),
            'qos' => $this->smallInteger()->notNull()->defaultValue(0)->comment('qos'),
            'created_at' => $this->dateTime(6)->null()->comment('created at'),
            'updated_at' => $this->dateTime(6)->null()->comment('updated at'),
        ]);

        $this->createIndex('client_id', '{{%mqtt_subscription}}', 'client_id');
        $this->createIndex('topic', '{{%mqtt_subscription}}', 'topic');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%mqtt_subscription}}');
    }
}
