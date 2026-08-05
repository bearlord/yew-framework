<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;

use Exception;
use Yew\Core\Server\Server;
use Monolog\Logger;

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
        $this->addRecord($level, $message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     * @param $message
     * @param array|null $context
     * @return void
     */
    public function debug($message, ?array $context = [])
    {
        $this->addRecord(Logger::DEBUG, $message, $context);
    }

    /**
     * @param int $level
     * @param $message
     * @param array|null $context
     * @return void
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
     */
    public function warn($message, ?array $context = [])
    {
        $this->addRecord(Logger::WARNING, $message, $context);
    }

    /**
     * Alias of warn().
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void
     */
    public function warning($message, ?array $context = [])
    {
        $this->warn($message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     */
    public function err($message, ?array $context = [])
    {
        $this->addRecord(Logger::ERROR, $message, $context);
    }

    /**
     * Alias of err().
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void
     */
    public function error($message, ?array $context = [])
    {
        $this->err($message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
     */
    public function crit($message, ?array $context = [])
    {
        $this->addRecord(Logger::CRITICAL, $message, $context);
    }

    /**
     * Alias of crit().
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void
     */
    public function critical($message, ?array $context = [])
    {
        $this->crit($message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void Whether the record has been processed
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
     */
    public function emerg($message, ?array $context = [])
    {
        $this->addRecord(Logger::EMERGENCY, $message, $context);
    }

    /**
     * Alias of emerg().
     *
     * @param mixed $message The log message
     * @param array|null $context The log context
     * @return void
     */
    public function emergency($message, ?array $context = [])
    {
        $this->emerg($message, $context);
    }
}
