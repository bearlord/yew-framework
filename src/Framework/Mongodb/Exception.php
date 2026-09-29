<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yew\Framework\Mongodb;

/**
 * Exception represents an exception that is caused by some Mongo-related operations.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Exception extends \Yew\Framework\Exception\Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'MongoDB Exception';
    }
}
