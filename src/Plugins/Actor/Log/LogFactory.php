<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Log;

/**
 * Assembles a per-actor Logger: FileTarget -> Dispatcher -> Logger.
 *
 * The returned Logger is ready to use and writes to a file named after the actor.
 */
class LogFactory
{
    /**
     * @param string $name Actor name; also used as the log file base name.
     * @param ?string $logDir Absolute directory for the log file. Falls back to
     *                         the framework runtime path (logs/actors) when null.
     */
    public static function create(string $name, ?string $logDir = null): Logger
    {
        $target = new FileTarget($name, $logDir);
        $dispatcher = new Dispatcher($target);

        return new Logger($dispatcher);
    }
}
