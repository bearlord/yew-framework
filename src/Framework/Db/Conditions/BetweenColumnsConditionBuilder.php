<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Conditions;

use Yew\Framework\Db\Exception;
use Yew\Framework\Db\ExpressionBuilderInterface;
use Yew\Framework\Db\ExpressionBuilderTrait;
use Yew\Framework\Db\ExpressionInterface;
use Yew\Framework\Db\Query;

/**
 * Class BetweenColumnsConditionBuilder builds objects of [[BetweenColumnsCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class BetweenColumnsConditionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|BetweenColumnsCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     * @throws Exception
     */
    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $operator = $expression->getOperator();

        $startColumn = $this->escapeColumnName($expression->getIntervalStartColumn(), $params);
        $endColumn = $this->escapeColumnName($expression->getIntervalEndColumn(), $params);
        $value = $this->createPlaceholder($expression->getValue(), $params);

        return "$value $operator $startColumn AND $endColumn";
    }

    /**
     * Prepares column name to be used in SQL statement.
     *
     * @param Query|ExpressionInterface|string $columnName
     * @param array $params the binding parameters.
     * @return string
     * @throws Exception
     */
    protected function escapeColumnName($columnName, array &$params = [])
    {
        if ($columnName instanceof Query) {
            list($sql, $params) = $this->queryBuilder->build($columnName, $params);
            return "($sql)";
        } elseif ($columnName instanceof ExpressionInterface) {
            return $this->queryBuilder->buildExpression($columnName, $params);
        } elseif (strpos($columnName, '(') === false) {
            return $this->queryBuilder->db->quoteColumnName($columnName);
        }

        return $columnName;
    }

    /**
     * Attaches $value to $params array and returns placeholder.
     *
     * @param mixed $value
     * @param array $params passed by reference
     * @return string
     */
    protected function createPlaceholder($value, array &$params): string
    {
        if ($value instanceof ExpressionInterface) {
            return $this->queryBuilder->buildExpression($value, $params);
        }

        return $this->queryBuilder->bindParam($value, $params);
    }
}
