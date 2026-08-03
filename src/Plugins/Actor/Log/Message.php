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
    public function __construct(
        public readonly string $level,
        public readonly mixed $body,
        public readonly float $timestamp,
        public readonly array $context = []
    ) {
    }

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
