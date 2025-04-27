<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Base;

use Yew\Yew;

/**
 * StaticInstanceTrait provides methods to satisfy [[StaticInstanceInterface]] interface.
 *
 * @see StaticInstanceInterface
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.13
 */
trait StaticInstanceTrait
{
    /**
     * @var static[] static instances in format: `[className => object]`
     */
    private static $_instances = [];


    /**
     * Returns static class instance, which can be used to obtain meta information.
     * @param bool $refresh whether to re-create static instance even, if it is already cached.
     * @return \Yew\Framework\Base\StaticInstanceTrait|\Yew\Framework\Base\Model class instance.
     * @throws \Yew\Framework\Exception\InvalidConfigException
     */
    public static function instance(?bool $refresh = false): self
    {
        $className = get_called_class();
        if ($refresh || !isset(self::$_instances[$className])) {
            self::$_instances[$className] = Yii::createObject($className);
        }
        return self::$_instances[$className];
    }
}
