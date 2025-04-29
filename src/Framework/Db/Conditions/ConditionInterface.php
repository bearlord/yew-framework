<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Conditions;

use Yew\Framework\Db\ExpressionInterface;
use Yew\Framework\Exception\InvalidArgumentException;

/**
 * Interface ConditionInterface should be implemented by classes that represent a condition
 * in DBAL of framework.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
interface ConditionInterface extends ExpressionInterface
{
    /**
     * Creates object by array-definition as described in
     * [Query Builder – Operator format](guide:db-query-builder#operator-format) guide article.
     *
     * @param string $operator operator in uppercase.
     * @param array $operands array of corresponding operands
     *
     * @return $this
     * @throws InvalidArgumentException if input parameters are not suitable for this condition
     */
    public static function fromArrayDefinition(string $operator, array $operands): ConditionInterface;
}
