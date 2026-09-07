<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Log;

use Yew\Framework\Base\Component;

/**
 * Base class for log targets.
 *
 * A target filters the messages dispatched by the {@see Logger} (by level and
 * enabled state) and exports the survivors to a destination (file, syslog, …).
 * Subclasses implement {@see export()}.
 */
abstract class Target extends Component
{
    /**
     * @var int how many messages to accumulate before exporting (0 = only on flush)
     */
    public int $exportInterval = 1000;

    /**
     * @var Message[] messages collected from the logger, pending export
     */
    public array $messages = [];

    /**
     * @var bool whether to include microseconds in the timestamp
     */
    public bool $microtime = false;

    /** @var bool|callable */
    private $enabled = true;

    /**
     * Re-entrancy guard: true while {@see export()} is running, so a flush
     * triggered from within export() cannot recurse into another export.
     */
    private bool $exporting = false;

    /**
     * Export collected messages to the destination. Implemented by subclasses.
     */
    abstract public function export(): void;

    /**
     * Collect dispatched messages and export them if the interval is reached
     * (or this is the final flush).
     *
     * @param Message[] $messages
     */
    public function collect(array $messages, bool $final): void
    {
        if (!$this->getEnabled()) {
            return;
        }

        foreach ($messages as $message) {
            $this->messages[] = $message;
        }

        $count = count($this->messages);
        if ($count === 0 || (!$final && $count < $this->exportInterval)) {
            return;
        }

        if ($this->exporting) {
            return;
        }
        $this->exporting = true;
        try {
            $this->export();
        } finally {
            $this->exporting = false;
        }
        $this->messages = [];
    }

    public function formatMessage(Message $message): string
    {
        return sprintf(
            "%s %s %s",
            $this->getTime($message->timestamp),
            Level::tag($message->level),
            $message->text()
        );
    }

    /** @param bool|callable $value */
    public function setEnabled($value): void
    {
        $this->enabled = $value;
    }

    public function getEnabled(): bool
    {
        return is_callable($this->enabled)
            ? (bool) call_user_func($this->enabled, $this)
            : $this->enabled;
    }

    /**
     * Format a timestamp as "Y-m-d H:i:s" (or "Y-m-d H:i:s.u" when microtime is on).
     */
    protected function getTime(float $timestamp): string
    {
        $parts = explode('.', sprintf('%F', $timestamp));

        return date('Y-m-d H:i:s', (int) $parts[0])
            . ($this->microtime && isset($parts[1]) ? '.' . $parts[1] : '');
    }
}
