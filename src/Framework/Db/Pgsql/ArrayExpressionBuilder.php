<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Pgsql;

use Yew\Framework\Db\ArrayExpression;
use Yew\Framework\Db\Exception;
use Yew\Framework\Db\ExpressionBuilderInterface;
use Yew\Framework\Db\ExpressionBuilderTrait;
use Yew\Framework\Db\ExpressionInterface;
use Yew\Framework\Db\JsonExpression;
use Yew\Framework\Db\Query;

/**
 * Class ArrayExpressionBuilder builds [[ArrayExpression]] for PostgreSQL DBMS.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class ArrayExpressionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * {@inheritdoc}
     * @param ArrayExpression|ExpressionInterface $expression the expression to be built
     * @param array $params
     * @return string
     * @throws Exception
     */
    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $value = $expression->getValue();
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof Query) {
            list ($sql, $params) = $this->queryBuilder->build($value, $params);
            return $this->buildSubqueryArray($sql, $expression);
        }

        $placeholders = $this->buildPlaceholders($expression, $params);

        return 'ARRAY[' . implode(', ', $placeholders) . ']' . $this->getTypehint($expression);
    }

    /**
     * Builds placeholders array out of $expression values
     * @param ExpressionInterface|ArrayExpression $expression
     * @param array $params the binding parameters.
     * @return array
     * @throws Exception
     */
    protected function buildPlaceholders(ExpressionInterface $expression, array &$params): array
    {
        $value = $expression->getValue();

        $placeholders = [];
        if ($value === null || !is_array($value) && !$value instanceof \Traversable) {
            return $placeholders;
        }

        if ($expression->getDimension() > 1) {
            foreach ($value as $item) {
                $placeholders[] = $this->build($this->unnestArrayExpression($expression, $item), $params);
            }
            return $placeholders;
        }

        foreach ($value as $item) {
            if ($item instanceof Query) {
                list ($sql, $params) = $this->queryBuilder->build($item, $params);
                $placeholders[] = $this->buildSubqueryArray($sql, $expression);
                continue;
            }

            $item = $this->typecastValue($expression, $item);
            if ($item instanceof ExpressionInterface) {
                $placeholders[] = $this->queryBuilder->buildExpression($item, $params);
                continue;
            }

            $placeholders[] = $this->queryBuilder->bindParam($item, $params);
        }

        return $placeholders;
    }

    /**
     * @param ArrayExpression $expression
     * @param mixed $value
     * @return ArrayExpression
     */
    private function unnestArrayExpression(ArrayExpression $expression, $value): ArrayExpression
    {
        $expressionClass = get_class($expression);

        return new $expressionClass($value, $expression->getType(), $expression->getDimension()-1);
    }

    /**
     * @param ArrayExpression $expression
     * @return string the typecast expression based on [[type]].
     */
    protected function getTypehint(ArrayExpression $expression): string
    {
        if ($expression->getType() === null) {
            return '';
        }

        $result = '::' . $expression->getType();
        $result .= str_repeat('[]', $expression->getDimension());

        return $result;
    }

    /**
     * Build an array expression from a subquery SQL.
     *
     * @param string $sql the subquery SQL.
     * @param ArrayExpression $expression
     * @return string the subquery array expression.
     */
    protected function buildSubqueryArray(string $sql, ArrayExpression $expression): string
    {
        return 'ARRAY(' . $sql . ')' . $this->getTypehint($expression);
    }

    /**
     * Casts $value to use in $expression
     *
     * @param ArrayExpression $expression
     * @param mixed $value
     * @return ExpressionInterface
     */
    protected function typecastValue(ArrayExpression $expression, $value)
    {
        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if (in_array($expression->getType(), [Schema::TYPE_JSON, Schema::TYPE_JSONB], true)) {
            return new JsonExpression($value);
        }

        return $value;
    }
}
