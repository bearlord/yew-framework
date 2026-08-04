<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Log;

use Yew\Framework\Base\Component;

/**
 * Collects and flushes actor log messages.
 *
 * Each actor owns one Logger (via LogFactory). Messages are buffered and
 * flushed to the Dispatcher either when {@see flushInterval} is reached or on
 * demand. A flushInterval of 1 (the default for actors) emits every message
 * immediately — suitable for per-actor audit trails.
 */
class Logger extends Component
{
    /**
     * @var Message[] buffered messages awaiting flush
     */
    public array $messages = [];

    /**
     * @var int how many messages to accumulate before a flush (0 = only on shutdown)
     */
    public int $flushInterval = 1;

    public Dispatcher $dispatcher;

    /**
     * Build a Logger bound to a dispatcher.
     *
     * @param Dispatcher $dispatcher Target dispatcher for flushed messages
     * @param int $flushInterval Messages to buffer before auto-flush (1 = immediate)
     */
    public function __construct(Dispatcher $dispatcher, int $flushInterval = 1)
    {
        $this->dispatcher = $dispatcher;
        $this->flushInterval = $flushInterval;
    }

    /**
     * Record a message.
     *
     * @param mixed $message The log body (string, array, or Stringable).
     * @param string $level One of the Level::* constants.
     * @param array $context Optional structured context.
     */
    public function log(mixed $message, string $level = Level::INFO, array $context = []): void
    {
        $this->messages[] = new Message($level, $message, microtime(true), $context);

        if ($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval) {
            $this->flush();
        }
    }

    /**
     * Convenience level helpers (keep call sites readable).
     */
    public function debug(mixed $message, array $context = []): void
    {
        $this->log($message, Level::DEBUG, $context);
    }

    public function info(mixed $message, array $context = []): void
    {
        $this->log($message, Level::INFO, $context);
    }

    public function warning(mixed $message, array $context = []): void
    {
        $this->log($message, Level::WARNING, $context);
    }

    public function error(mixed $message, array $context = []): void
    {
        $this->log($message, Level::ERROR, $context);
    }

    /**
     * Flush buffered messages to the dispatcher and clear the buffer.
     *
     * @param bool|null $final True when flushing at shutdown
     */
    public function flush(?bool $final = false): void
    {
        if ($this->messages === []) {
            return;
        }
        $messages = $this->messages;
        $this->messages = [];

        if (isset($this->dispatcher)) {
            $this->dispatcher->dispatch($messages, $final);
        }
    }
}
