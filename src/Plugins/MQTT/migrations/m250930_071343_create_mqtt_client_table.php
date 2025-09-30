<?php

use Yew\Framework\Db\Migration;

/**
 * Handles the creation of table `{{%mqtt_client}}`.
 */
class m250930_071343_create_mqtt_client_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%mqtt_client}}', [
            'id' => $this->bigPrimaryKey()->comment('primary key'),
            'client_id' => $this->string(128)->notNull()->comment('client id'),
            'username' => $this->string(128)->null()->comment('username'),
            'password' => $this->string(128)->null()->comment('password'),
            'is_active' => $this->smallInteger()->null()->defaultValue(0)->comment('0: inactive, 1: active'),
            'last_connected_time' => $this->dateTime(6)->null()->comment('last connected at'),
            'last_communication_time' => $this->dateTime(6)->null()->comment('last communication'),
            'last_disconnected_time' => $this->dateTime(6)->null()->comment('last disconnected at'),
            'ip_address' => $this->string(45)->null()->comment('ip address'),
            'clean_session' => $this->smallInteger()->null()->defaultValue(0)->comment('0: not clean session, 1: clean session'),
            'created_at' => $this->dateTime(6)->null()->comment('created at'),
            'updated_at' => $this->dateTime(6)->null()->comment('updated at'),
        ]);

        $this->createIndex('client_id', '{{%mqtt_client}}', 'client_id');
        $this->createIndex('username', '{{%mqtt_client}}', 'username');
        $this->createIndex('is_active', '{{%mqtt_client}}', 'is_active');
        $this->createIndex('last_connected_time', '{{%mqtt_client}}', 'last_connected_time');
        $this->createIndex('last_communication_time', '{{%mqtt_client}}', 'last_communication_time');
        $this->createIndex('last_disconnected_time', '{{%mqtt_client}}', 'last_disconnected_time');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%mqtt_client}}');
    }
}
