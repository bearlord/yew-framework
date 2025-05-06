<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;

use Yew\Core\Exception\Exception;

class Logger extends \Monolog\Logger
{
    /**
     * @param $level
     * @param $message
     * @param array $context
     * @return bool
     */
    public function addRecord($level, $message, array $context = array()): bool
    {
        if ($message instanceof Exception) {
            if (!$message->isTrace()) {
                return parent::addRecord(\Monolog\Logger::DEBUG, $message, $context);
            }
        }
        return parent::addRecord($level, $message, $context);
    }
}