<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Cubrid\conditions;

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
}
