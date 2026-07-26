<?php

namespace Yew\Plugins\Mqtt\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_subscription}}".
 *
 * @property int $id primary key
 * @property string $client_id client id
 * @property string $topic topic
 * @property int $qos qos
 * @property string|null $created_at created at
 * @property string|null $updated_at updated at
 */
class MqttSubscription extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_subscription}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['client_id', 'topic'], 'required'],
            [['qos'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
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
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
