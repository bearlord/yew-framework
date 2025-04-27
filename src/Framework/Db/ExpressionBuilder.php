<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db;

/**
 * Class ExpressionBuilder builds objects of [[Yew\Framework\Db\Expression]] class.
 *
 * @author Dmitry Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class ExpressionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;

    /**
     * @param ExpressionInterface $expression
     * @param array $params
     * @return string
     */
    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $params = array_merge($params, $expression->params);
        return $expression->__toString();
    }
}
