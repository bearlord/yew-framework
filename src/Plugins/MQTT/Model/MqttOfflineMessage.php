<?php

namespace Yew\Plugins\Mqtt\Model;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_offline_message}}".
 *
 * @property int $id primary key
 * @property string $client_id client id
 * @property string $topic topic
 * @property int $qos qos
 * @property resource $payload payload
 * @property int $delivered delivered. 0: not delivered, 1: delivered
 * @property string|null $delivered_at delivered at
 * @property string|null $created_at created at
 * @property string|null $updated_at updated at
 */
class MqttOfflineMessage extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_offline_message}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['client_id', 'topic', 'payload'], 'required'],
            [['qos', 'delivered'], 'integer'],
            [['payload'], 'string'],
            [['delivered_at', 'created_at', 'updated_at'], 'safe'],
            [['client_id'], 'string', 'max' => 128],
            [['topic'], 'string', 'max' => 240],
        ];
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'client_id' => 'Client ID',
            'topic' => 'Topic',
            'qos' => 'Qos',
            'payload' => 'Payload',
            'delivered' => 'Delivered',
            'delivered_at' => 'Delivered At',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
