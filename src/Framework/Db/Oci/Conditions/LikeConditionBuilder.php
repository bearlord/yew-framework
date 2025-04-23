<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Oci\conditions;

use Yew\Framework\Db\ExpressionInterface;

/**
 * {@inheritdoc}
 */
class LikeConditionBuilder extends \Yew\Framework\Db\Conditions\LikeConditionBuilder
{
    /**
     * {@inheritdoc}
     */
    protected ?string $escapeCharacter = '!';
    /**
     * `\` is initialized in [[buildLikeCondition()]] method since
     * we need to choose replacement value based on [[\Yew\Framework\Db\Schema::quoteValue()]].
     * {@inheritdoc}
     */
    protected array $escapingReplacements = [
        '%' => '!%',
        '_' => '!_',
        '!' => '!!',
    ];


    /**
     * {@inheritdoc}
     */
    public function build(ExpressionInterface $expression, array &$params = [])
    {
        if (!isset($this->escapingReplacements['\\'])) {
            /*
             * Different pdo_oci8 versions may or may not implement PDO::quote(), so
             * Yew\Framework\Db\Schema::quoteValue() may or may not quote \.
             */
            $this->escapingReplacements['\\'] = substr($this->queryBuilder->db->quoteValue('\\'), 1, -1);
        }

        return parent::build($expression, $params);
    }
}
