<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yew\Framework\Mongodb\Validators;

use MongoDB\BSON\ObjectID;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Validators\Validator;
use Yew\Yew;

/**
 * MongoIdValidator verifies if the attribute is a valid Mongo ID.
 * Attribute will be considered as valid, if it is an instance of [[\MongoId]] or a its string value.
 *
 * Usage example:
 *
 * ```php
 * class Customer extends Yew\Framework\Mongodb\ActiveRecord
 * {
 *     ...
 *     public function rules()
 *     {
 *         return [
 *             ['_id', 'Yew\Framework\Mongodb\Validators\MongoIdValidator']
 *         ];
 *     }
 * }
 * ```
 *
 * This validator may also serve as a filter, allowing conversion of Mongo ID value either to the plain string
 * or to [[\MongoId]] instance. You can enable this feature via [[forceFormat]].
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class MongoIdValidator extends Validator
{
    /**
     * @var string|null specifies the format, which validated attribute value should be converted to
     * in case validation was successful.
     * valid values are:
     * - 'string' - enforce value converted to plain string.
     * - 'object' - enforce value converted to [[\MongoId]] instance.
     *   If not set - no conversion will be performed, leaving attribute value intact.
     */
    public ?string $forceFormat;


    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yew::t('yew', '{attribute} is invalid.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute): void
    {
        $value = $model->$attribute;
        $mongoId = $this->parseMongoId($value);
        if (is_object($mongoId)) {
            if ($this->forceFormat !== null) {
                switch ($this->forceFormat) {
                    case 'string' : {
                        $model->$attribute = $mongoId->__toString();
                        break;
                    }
                    case 'object' : {
                        $model->$attribute = $mongoId;
                        break;
                    }
                    default: {
                        throw new InvalidConfigException("Unrecognized format '{$this->forceFormat}'");
                    }
                }
            }
        } else {
            $this->addError($model, $attribute, $this->message, []);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        return is_object($this->parseMongoId($value)) ? null : [$this->message, []];
    }

    /**
     * @param mixed $value
     * @return ObjectID|null
     */
    private function parseMongoId($value): ?ObjectID
    {
        if ($value instanceof ObjectID) {
            return $value;
        }
        try {
            return new ObjectID($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
