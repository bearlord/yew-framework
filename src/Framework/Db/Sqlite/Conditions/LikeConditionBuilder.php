<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Sqlite\conditions;

/**
 * {@inheritdoc}
 */
class LikeConditionBuilder extends \Yew\Framework\Db\Conditions\LikeConditionBuilder
{
    /**
     * {@inheritdoc}
     */
    protected ?string $escapeCharacter = '\\';
}
