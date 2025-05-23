<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Log;

use Yew\Framework\Helpers\VarDumper;

/**
 * SyslogTarget writes log to syslog.
 *
 * @author miramir <gmiramir@gmail.com>
 * @since 2.0
 */
class SyslogTarget extends Target
{
    /**
     * @var string syslog identity
     */
    public string $identity;
    /**
     * @var int syslog facility.
     */
    public int $facility = LOG_USER;
    /**
     * @var int|null openlog options. This is a bitfield passed as the `$option` parameter to [openlog()](https://secure.php.net/openlog).
     * Defaults to `null` which means to use the default options `LOG_ODELAY | LOG_PID`.
     * @see https://secure.php.net/openlog for available options.
     * @since 2.0.11
     */
    public ?int $options = null;

    /**
     * @var array syslog levels
     */
    private array $_syslogLevels = [
        Logger::LEVEL_TRACE => LOG_DEBUG,
        Logger::LEVEL_PROFILE_BEGIN => LOG_DEBUG,
        Logger::LEVEL_PROFILE_END => LOG_DEBUG,
        Logger::LEVEL_PROFILE => LOG_DEBUG,
        Logger::LEVEL_INFO => LOG_INFO,
        Logger::LEVEL_WARNING => LOG_WARNING,
        Logger::LEVEL_ERROR => LOG_ERR,
    ];


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->options === null) {
            $this->options = LOG_ODELAY | LOG_PID;
        }
    }

    /**
     * Writes log messages to syslog.
     * Starting from version 2.0.14, this method throws LogRuntimeException in case the log can not be exported.
     * @throws LogRuntimeException
     */
    public function export()
    {
        openlog($this->identity, $this->options, $this->facility);
        foreach ($this->messages as $message) {
            if (syslog($this->_syslogLevels[$message[1]], $this->formatMessage($message)) === false) {
                throw new LogRuntimeException('Unable to export log through system log!');
            }
        }
        closelog();
    }

    /**
     * {@inheritdoc}
     */
    public function formatMessage($message): string
    {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string) $text;
            } else {
                $text = VarDumper::export($text);
            }
        }

        $prefix = $this->getMessagePrefix($message);
        return "{$prefix}[$level][$category] $text";
    }
}
