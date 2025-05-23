<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Conditions;

use Yew\Framework\Exception\InvalidArgumentException;

/**
 * Class SimpleCondition represents a simple condition like `"column" operator value`.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class SimpleCondition implements ConditionInterface
{
    /**
     * @var string $operator the operator to use. Anything could be used e.g. `>`, `<=`, etc.
     */
    private string $operator;
    /**
     * @var mixed the column name to the left of [[operator]]
     */
    private $column;
    /**
     * @var mixed the value to the right of the [[operator]]
     */
    private $value;


    /**
     * SimpleCondition constructor
     *
     * @param mixed $column the literal to the left of $operator
     * @param string $operator the operator to use. Anything could be used e.g. `>`, `<=`, etc.
     * @param mixed $value the literal to the right of $operator
     */
    public function __construct($column, string $operator, $value)
    {
        $this->column = $column;
        $this->operator = $operator;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return mixed
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException if wrong number of operands have been given.
     */
    public static function fromArrayDefinition(string $operator, array $operands): ConditionInterface
    {
        if (count($operands) !== 2) {
            throw new InvalidArgumentException("Operator '$operator' requires two operands.");
        }

        return new static($operands[0], $operator, $operands[1]);
    }
}
