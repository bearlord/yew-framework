<?php

namespace Yew\Plugins\Mqtt\Model;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_client}}".
 *
 * @property int $id primary key
 * @property string $client_id client id
 * @property string|null $username username
 * @property string|null $password password
 * @property int|null $is_active 0: inactive, 1: active
 * @property string|null $last_connected_time last connected at
 * @property string|null $last_communication_time last communication
 * @property string|null $last_disconnected_time last disconnected at
 * @property string|null $ip_address ip address
 * @property int|null $clean_session 0: not clean session, 1: clean session
 * @property string|null $created_at created at
 * @property string|null $updated_at updated at
 */
class MqttClient extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_client}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['client_id'], 'required'],
            [['is_active', 'clean_session'], 'integer'],
            [['last_connected_time', 'last_communication_time', 'last_disconnected_time', 'created_at', 'updated_at'], 'safe'],
            [['client_id', 'username', 'password'], 'string', 'max' => 128],
            [['ip_address'], 'string', 'max' => 45],
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
            'username' => 'Username',
            'password' => 'Password',
            'is_active' => 'Is Active',
            'last_connected_time' => 'Last Connected Time',
            'last_communication_time' => 'Last Communication Time',
            'last_disconnected_time' => 'Last Disconnected Time',
            'ip_address' => 'Ip Address',
            'clean_session' => 'Clean Session',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
