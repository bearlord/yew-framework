<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Base;

/**
 * Configurable is the interface that should be implemented by classes who support configuring
 * its properties through the last parameter to its constructor.
 *
 * The interface does not declare any method. Classes implementing this interface must declare their constructors
 * like the following:
 *
 * ```php
 * public function __constructor($param1, $param2, ..., $config = [])
 * ```
 *
 * That is, the last parameter of the constructor must accept a configuration array.
 *
 * This interface is mainly used by [[\Yew\Framework\Di\Container]] so that it can pass object configuration as the
 * last parameter to the implementing class' constructor.
 *
 * For more details and usage information on Configurable, see the [guide article on configurations](guide:concept-configurations).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0.3
 */
interface Configurable
{
}
