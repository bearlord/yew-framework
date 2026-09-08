<?php

namespace Yew\Plugins\Mqtt\Models;

use Yew\Framework\Db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property int $enabled
 * @property string $source
 * @property string|null $filter
 * @property string $actions
 * @property int $priority
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class MqttRule extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_rule}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'source', 'actions'], 'required'],
            [['enabled', 'priority'], 'integer'],
            [['enabled'], 'default', 'value' => 1],
            [['priority'], 'default', 'value' => 0],
            [['filter', 'actions'], 'string'],
            [['source'], 'string', 'max' => 64],
            [['name'], 'string', 'max' => 128],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'enabled' => 'Enabled',
            'source' => 'Source',
            'filter' => 'Filter',
            'actions' => 'Actions',
            'priority' => 'Priority',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Decode the JSON action list into an array.
     *
     * @return array
     */
    public function getActionsDecoded(): array
    {
        $decoded = json_decode($this->actions, true);

        return is_array($decoded) ? $decoded : [];
    }
}
