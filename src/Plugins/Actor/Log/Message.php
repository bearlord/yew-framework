<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Log;

use Yew\Framework\Helpers\Json;

/**
 * Immutable log message.
 *
 * Replaces the previous bare `[$body, $timestamp]` array with a typed value
 * object so the message structure is explicit along the whole
 * Logger -> Dispatcher -> Target pipeline.
 */
final class Message
{
    /**
     * Create an immutable log message.
     *
     * @param string $level One of the Level::* constants
     * @param mixed $body Message body (string, array, or Stringable)
     * @param float $timestamp Capture time (seconds)
     * @param array $context Optional structured context
     */
    public function __construct(
        public readonly string $level,
        public readonly mixed $body,
        public readonly float $timestamp,
        public readonly array $context = []
    ) {
    }

    /**
     * Render the body as a string for output.
     *
     * @return string
     */
    public function text(): string
    {
        if (is_string($this->body)) {
            return $this->body;
        }
        if ($this->body instanceof \Stringable) {
            return (string) $this->body;
        }
        return Json::encode($this->body);
    }
}
