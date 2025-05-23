<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Conditions;

use Yew\Framework\Exception\InvalidArgumentException;
use Yew\Framework\Db\ExpressionInterface;

/**
 * Class InCondition represents `IN` condition.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class InCondition implements ConditionInterface
{
    /**
     * @var string $operator the operator to use (e.g. `IN` or `NOT IN`)
     */
    private string $operator;
    /**
     * @var string|string[] the column name. If it is an array, a composite `IN` condition
     * will be generated.
     */
    private $column;
    /**
     * @var ExpressionInterface[]|string[]|int[] an array of values that [[column]] value should be among.
     * If it is an empty array the generated expression will be a `false` value if
     * [[operator]] is `IN` and empty if operator is `NOT IN`.
     */
    private array $values;


    /**
     * SimpleCondition constructor
     *
     * @param string|string[] $column the column name. If it is an array, a composite `IN` condition
     * will be generated.
     * @param string $operator the operator to use (e.g. `IN` or `NOT IN`)
     * @param array $values an array of values that [[column]] value should be among. If it is an empty array the generated
     * expression will be a `false` value if [[operator]] is `IN` and empty if operator is `NOT IN`.
     */
    public function __construct($column, string $operator, array $values)
    {
        $this->column = $column;
        $this->operator = $operator;
        $this->values = $values;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return string|string[]
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return ExpressionInterface[]|string[]|int[]
     */
    public function getValues(): array
    {
        return $this->values;
    }
    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException if wrong number of operands have been given.
     */
    public static function fromArrayDefinition(string $operator, array $operands): ConditionInterface
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidArgumentException("Operator '$operator' requires two operands.");
        }

        return new static($operands[0], $operator, $operands[1]);
    }
}
