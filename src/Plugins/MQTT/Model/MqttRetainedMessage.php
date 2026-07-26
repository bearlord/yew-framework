<?php

namespace Yew\Plugins\Mqtt\Model;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_retained_message}}".
 *
 * @property int $id primary key
 * @property string $topic topic
 * @property int $qos qos
 * @property int $retain retain
 * @property resource $payload payload
 * @property string|null $created_at created at
 * @property string|null $updated_at updated at
 */
class MqttRetainedMessage extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_retained_message}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['topic', 'payload'], 'required'],
            [['qos', 'retain'], 'integer'],
            [['payload'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
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
            'topic' => 'Topic',
            'qos' => 'Qos',
            'retain' => 'Retain',
            'payload' => 'Payload',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
