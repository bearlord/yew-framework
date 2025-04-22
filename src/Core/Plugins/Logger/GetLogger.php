<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;

use Exception;
use Yew\Core\Server\Server;
use Monolog\Logger;

/**
 * Trait GetLogger
 * @package Yew\Core\Plugins\Logger
 */
trait GetLogger
{
    /**
     * @param $level
     * @param $message
     * @param array $context
     * @return void
     * @throws Exception
     */
    public function log($level, $message, array $context = array())
    {
        Server::$instance->getLog()->log($level, $message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     * @param $message
     * @param array|null $context
     * @return void
     */
    public function debug($message, ?array $context = [])
    {
        try {
            $this->addRecord(Logger::DEBUG, $message, $context);
        } catch (\Exception $exception) {
            //do nothing
        }
    }

    /**
     * Adds a log record.
     *
     * @param int $level
     * @param $message
     * @param array|null $context
     * @return void
     * @throws Exception
     */
    public function addRecord(int $level, $message, ?array $context = [])
    {
        Server::$instance->getLog()->log($level, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function info($message, ?array $context = [])
    {
        $this->addRecord(Logger::INFO, $message, $context);
    }

    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function notice($message, ?array $context = [])
    {
        $this->addRecord(Logger::NOTICE, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function warn($message, ?array $context = [])
    {
        $this->addRecord(Logger::WARNING, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function warning($message, ?array $context = [])
    {
        $this->addRecord(Logger::WARNING, $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function err($message, ?array $context = [])
    {
        $this->addRecord(Logger::ERROR, $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function error($message, ?array $context = [])
    {
        $this->addRecord(Logger::ERROR, $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function crit($message, ?array $context = [])
    {
        $this->addRecord(Logger::CRITICAL, $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function critical($message, ?array $context = [])
    {
        $this->addRecord(Logger::CRITICAL, $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function alert($message, ?array $context = [])
    {
        $this->addRecord(Logger::ALERT, $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function emerg($message, ?array $context = [])
    {
        $this->addRecord(Logger::EMERGENCY, $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     * @throws Exception
     */
    public function emergency($message, ?array $context = [])
    {
        $this->addRecord(Logger::EMERGENCY, $message, $context);
    }
}
