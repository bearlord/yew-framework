<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Redis;

use Yew\Framework\Db\Exception;

/**
 * SocketException indicates a socket connection failure in [[Connection]].
 * @since 2.0.7
 */
class SocketException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Redis Socket Exception';
    }
}
