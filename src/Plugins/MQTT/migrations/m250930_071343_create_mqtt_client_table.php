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
            'id' => $this->bigPrimaryKey()->comment('Primary key'),

            'client_id' => $this->string(128)->notNull()->comment('MQTT client identifier'),
            'username' => $this->string(128)->null()->comment('Authentication username'),
            'password_hash' => $this->string(255)->null()->comment('Password hash (optional)'),

            'protocol_version' => $this->string(8)->notNull()->defaultValue('3.1.1')
                ->comment('MQTT protocol version (3.1, 3.1.1, 5.0)'),

            'clean_start' => $this->tinyInteger(1)->notNull()->defaultValue(1)
                ->comment('MQTT v5 clean start flag (1: clean, 0: resume session)'),

            'session_expiry' => $this->integer()->unsigned()->notNull()->defaultValue(0)
                ->comment('Session expiry interval in seconds (0 = never expire)'),

            'is_active' => $this->tinyInteger(1)->notNull()->defaultValue(1)
                ->comment('Client enabled flag (1: active, 0: disabled)'),

            'ip_address' => $this->string(45)->null()
                ->comment('Last connected IP address (IPv4/IPv6)'),

            'keep_alive' => $this->integer()->unsigned()->null()
                ->comment('Keep alive interval in seconds'),

            'last_connected_at' => $this->dateTime(6)->null()
                ->comment('Last successful connection time'),

            'last_disconnected_at' => $this->dateTime(6)->null()
                ->comment('Last disconnection time detected by broker'),

            'disconnect_reason' => $this->smallInteger()->null()->comment('MQTT v5 DISCONNECT reason code'),

            'created_at' => $this->dateTime(6)->notNull()->comment('Record creation time'),
            'updated_at' => $this->dateTime(6)->notNull()->comment('Record last update time'),
        ]);

        // Indexes
        $this->createIndex('uk_client_id', '{{%mqtt_client}}', 'client_id', true);
        $this->createIndex('idx_username', '{{%mqtt_client}}', 'username');
        $this->createIndex('idx_is_active', '{{%mqtt_client}}', 'is_active');
        $this->createIndex('idx_last_connected_at', '{{%mqtt_client}}', 'last_connected_at');
        $this->createIndex('idx_last_disconnected_at', '{{%mqtt_client}}', 'last_disconnected_at');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable("{{%mqtt_client}}");
    }
}
