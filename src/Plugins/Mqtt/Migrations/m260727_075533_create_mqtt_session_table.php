<?php

use Yew\Framework\Db\Migration;

/**
 * Handles the creation of table `{{%mqtt_session}}`.
 */
class m260727_075533_create_mqtt_session_table extends Migration
{
    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%mqtt_session}}', [
            'id' => $this->bigPrimaryKey()->comment('Primary key'),

            'client_id' => $this->string(128)->notNull()
                ->comment('MQTT client identifier'),

            'session_id' => $this->string(64)->notNull()
                ->comment('Broker internal session identifier (node+uuid)'),

            'clean_start' => $this->tinyInteger(1)->notNull()
                ->comment('MQTT v5 clean start flag (1: clean, 0: resume)'),

            'session_expiry' => $this->integer()->unsigned()->notNull()
                ->comment('Session expiry interval in seconds (0 = never expire)'),

            'is_online' => $this->tinyInteger(1)->defaultValue(0)
                ->comment('Session online status (1: online, 0: offline)'),

            'node' => $this->string(64)->null()
                ->comment('Broker node name (for cluster mode)'),

            'connected_at' => $this->dateTime(6)->null()
                ->comment('Session connection time (UTC)'),

            'disconnected_at' => $this->dateTime(6)->null()
                ->comment('Session disconnection time (UTC)'),

            'will_id' => $this->bigInteger()->unsigned()->null()
                ->comment('Reference to mqtt_will_messages.id'),

            'created_at' => $this->dateTime(6)->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP(6)')
                ->comment('Record creation time (UTC)'),

            'updated_at' => $this->dateTime(6)->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)')
                ->comment('Record last update time (UTC)'),
        ]);

        // Indexes
        $this->createIndex(
            'uk_client_session',
            '{{%mqtt_session}}',
            ['client_id', 'session_id'],
            true
        );

        $this->createIndex(
            'idx_client_id',
            '{{%mqtt_session}}',
            'client_id'
        );

        $this->createIndex(
            'idx_session_id',
            '{{%mqtt_session}}',
            'session_id'
        );

        $this->createIndex(
            'idx_is_online',
            '{{%mqtt_session}}',
            'is_online'
        );

        $this->createIndex(
            'idx_connected_at',
            '{{%mqtt_session}}',
            'connected_at'
        );

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function safeDown(): bool
    {
        $this->dropTable('{{%mqtt_session}}');

        return true;
    }
}
