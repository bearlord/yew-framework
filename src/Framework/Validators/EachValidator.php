<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Validators;

use Yew\Yew;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Base\Model;

/**
 * EachValidator validates an array by checking each of its elements against an embedded validation rule.
 *
 * ```php
 * class MyModel extends Model
 * {
 *     public $categoryIDs = [];
 *
 *     public function rules()
 *     {
 *         return [
 *             // checks if every category ID is an integer
 *             ['categoryIDs', 'each', 'rule' => ['integer']],
 *         ]
 *     }
 * }
 * ```
 *
 * > Note: This validator will not work with inline validation rules in case of usage outside the model scope,
 *   e.g. via [[validate()]] method.
 *
 * > Note: EachValidator is meant to be used only in basic cases, you should consider usage of tabular input,
 *   using several models for the more complex case.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class EachValidator extends Validator
{
    /**
     * @var array|Validator definition of the validation rule, which should be used on array values.
     * It should be specified in the same format as at [[\Yew\Framework\Base\Model::rules()]], except it should not
     * contain attribute list as the first element.
     * For example:
     *
     * ```php
     * ['integer']
     * ['match', 'pattern' => '/[a-z]/is']
     * ```
     *
     * Please refer to [[\Yew\Framework\Base\Model::rules()]] for more details.
     */
    public $rule;
    /**
     * @var bool whether to use error message composed by validator declared via [[rule]] if its validation fails.
     * If enabled, error message specified for this validator itself will appear only if attribute value is not an array.
     * If disabled, own error message value will be used always.
     */
    public $allowMessageFromRule = true;
    /**
     * @var bool whether to stop validation once first error among attribute value elements is detected.
     * When enabled validation will produce single error message on attribute, when disabled - multiple
     * error messages mya appear: one per each invalid value.
     * Note that this option will affect only [[validateAttribute()]] value, while [[validateValue()]] will
     * not be affected.
     * @since 2.0.11
     */
    public $stopOnFirstError = true;

    /**
     * @var Validator validator instance.
     */
    private $_validator;


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yew::t('yew', '{attribute} is invalid.');
        }
    }

    /**
     * Returns the validator declared in [[rule]].
     * @param Model|null $model model in which context validator should be created.
     * @return Validator the declared validator.
     */
    private function getValidator($model = null)
    {
        if ($this->_validator === null) {
            $this->_validator = $this->createEmbeddedValidator($model);
        }

        return $this->_validator;
    }

    /**
     * Creates validator object based on the validation rule specified in [[rule]].
     * @param Model|null $model model in which context validator should be created.
     * @throws \Yew\Framework\Exception\InvalidConfigException
     * @return Validator validator instance
     */
    private function createEmbeddedValidator($model)
    {
        $rule = $this->rule;
        if ($rule instanceof Validator) {
            return $rule;
        } elseif (is_array($rule) && isset($rule[0])) { // validator type
            if (!is_object($model)) {
                $model = new Model(); // mock up context model
            }

            return Validator::createValidator($rule[0], $model, $this->attributes, array_slice($rule, 1));
        }

        throw new InvalidConfigException('Invalid validation rule: a rule must be an array specifying validator type.');
    }

    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if (!is_array($value) && !$value instanceof \ArrayAccess) {
            $this->addError($model, $attribute, $this->message, []);
            return;
        }

        $validator = $this->getValidator($model); // ensure model context while validator creation

        $detectedErrors = $model->getErrors($attribute);
        $filteredValue = $model->$attribute;
        foreach ($value as $k => $v) {
            $model->clearErrors($attribute);
            $model->$attribute = $v;
            if (!$validator->skipOnEmpty || !$validator->isEmpty($v)) {
                $validator->validateAttribute($model, $attribute);
            }
            $filteredValue[$k] = $model->$attribute;
            if ($model->hasErrors($attribute)) {
                if ($this->allowMessageFromRule) {
                    $validationErrors = $model->getErrors($attribute);
                    $detectedErrors = array_merge($detectedErrors, $validationErrors);
                } else {
                    $model->clearErrors($attribute);
                    $this->addError($model, $attribute, $this->message, ['value' => $v]);
                    $detectedErrors[] = $model->getFirstError($attribute);
                }
                $model->$attribute = $value;

                if ($this->stopOnFirstError) {
                    break;
                }
            }
        }

        $model->$attribute = $filteredValue;
        $model->clearErrors($attribute);
        $model->addErrors([$attribute => $detectedErrors]);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        if (!is_array($value) && !$value instanceof \ArrayAccess) {
            $this->setValidCode(500001);
            return [$this->message, [], $this->getValidCode()];
        }

        $validator = $this->getValidator();
        foreach ($value as $v) {
            if ($validator->skipOnEmpty && $validator->isEmpty($v)) {
                continue;
            }
            $result = $validator->validateValue($v);
            if ($result !== null) {
                $this->setValidCode(500002);
                if ($this->allowMessageFromRule) {
                    $result[1]['value'] = $v;
                    $result[2] = $this->getValidCode();
                    return $result;
                }

                return [$this->message, ['value' => $v], $this->getValidCode()];
            }
        }

        return null;
    }
}
