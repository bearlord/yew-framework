<?php

namespace Yew\Plugins\Mqtt\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_message}}".
 *
 * @property int $id primary key
 * @property int $direction 0: up, 1: down
 * @property string $client_id client id
 * @property string $topic topic
 * @property string $payload payload
 * @property int $qos qos
 * @property int $retain retain
 * @property string $published_time published time
 * @property string $from_client_id from client id
 * @property string|null $created_at created at
 * @property string|null $updated_at updated at
 */
class MqttMessage extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_message}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['direction', 'qos', 'retain'], 'integer'],
            [['client_id', 'topic', 'payload', 'published_time', 'from_client_id'], 'required'],
            [['payload'], 'string'],
            [['published_time', 'created_at', 'updated_at'], 'safe'],
            [['client_id', 'from_client_id'], 'string', 'max' => 128],
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
            'direction' => 'Direction',
            'client_id' => 'Client ID',
            'topic' => 'Topic',
            'payload' => 'Payload',
            'qos' => 'Qos',
            'retain' => 'Retain',
            'published_time' => 'Published Time',
            'from_client_id' => 'From Client ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
