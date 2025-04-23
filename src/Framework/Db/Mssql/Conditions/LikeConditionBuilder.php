<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Mssql\conditions;

/**
 * {@inheritdoc}
 */
class LikeConditionBuilder extends \Yew\Framework\Db\Conditions\LikeConditionBuilder
{
    /**
     * {@inheritdoc}
     */
    protected array $escapingReplacements = [
        '%' => '[%]',
        '_' => '[_]',
        '[' => '[[]',
        ']' => '[]]',
        '\\' => '[\\]',
    ];
}
