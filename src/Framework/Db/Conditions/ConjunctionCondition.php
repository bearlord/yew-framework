<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Conditions;

/**
 * Class ConjunctionCondition
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
abstract class ConjunctionCondition implements ConditionInterface
{
    /**
     * @var array
     */
    protected $expressions;


    /**
     * @param mixed $expressions
     */
    public function __construct($expressions) // TODO: use variadic params when PHP>5.6
    {
        $this->expressions = $expressions;
    }

    /**
     * @return array
     */
    public function getExpressions(): array
    {
        return $this->expressions;
    }

    /**
     * Returns the operator that is represented by this condition class, e.g. `AND`, `OR`.
     * @return string
     */
    abstract public function getOperator(): string;

    /**
     * {@inheritdoc}
     */
    public static function fromArrayDefinition(string $operator, array $operands): ConditionInterface
    {
        return new static($operands);
    }
}
