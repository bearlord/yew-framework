<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Console;

use Yew\Framework\Exception\UserException;

/**
 * Exception represents an exception caused by incorrect usage of a console command.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Exception extends UserException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Error';
    }
}
