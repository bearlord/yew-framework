<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Conditions;

use Yew\Framework\Db\ExpressionBuilderInterface;
use Yew\Framework\Db\ExpressionBuilderTrait;
use Yew\Framework\Db\ExpressionInterface;

/**
 * Class ConjunctionConditionBuilder builds objects of abstract class [[ConjunctionCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class ConjunctionConditionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|ConjunctionCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $parts = $this->buildExpressionsFrom($expression, $params);

        if (empty($parts)) {
            return '';
        }

        if (count($parts) === 1) {
            return reset($parts);
        }

        return '(' . implode(") {$expression->getOperator()} (", $parts) . ')';
    }

    /**
     * Builds expressions, that are stored in $condition
     *
     * @param ExpressionInterface|ConjunctionCondition $condition the expression to be built.
     * @param array $params the binding parameters.
     * @return string[]
     */
    private function buildExpressionsFrom(ExpressionInterface $condition, array &$params = []): array
    {
        $parts = [];
        foreach ($condition->getExpressions() as $condition) {
            if (is_array($condition)) {
                $condition = $this->queryBuilder->buildCondition($condition, $params);
            }
            if ($condition instanceof ExpressionInterface) {
                $condition = $this->queryBuilder->buildExpression($condition, $params);
            }
            if ($condition !== '') {
                $parts[] = $condition;
            }
        }

        return $parts;
    }
}
