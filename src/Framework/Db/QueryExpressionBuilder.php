<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db;

use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Exception\NotSupportedException;

/**
 * Class QueryExpressionBuilder is used internally to build [[Query]] object
 * using unified [[QueryBuilder]] expression building interface.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class QueryExpressionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|Query $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     * @throws Exception
     * @throws \Throwable
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        list($sql, $params) = $this->queryBuilder->build($expression, $params);

        return "($sql)";
    }
}
