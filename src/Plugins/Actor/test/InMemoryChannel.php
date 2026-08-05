<?php

namespace Yew\Plugins\Actor\test;

/**
 * Stand-in for \Swoole\Channel used by offline tests when the Swoole extension
 * is not installed. Provides the subset of the API (constructor + push/pop)
 * that UdpGossipTransport relies on for its inbox.
 */
class InMemoryChannel
{
    /** @var array<int, mixed> */
    private array $queue = [];
    private int $capacity;

    public function __construct(int $capacity = 1024)
    {
        $this->capacity = $capacity;
    }

    public function push($data, float $timeout = -1): bool
    {
        if (count($this->queue) >= $this->capacity) {
            return false;
        }
        $this->queue[] = $data;
        return true;
    }

    public function pop(float $timeout = -1)
    {
        return array_shift($this->queue);
    }

    public function length(): int
    {
        return count($this->queue);
    }

    public function isEmpty(): bool
    {
        return $this->queue === [];
    }

    public function isFull(): bool
    {
        return count($this->queue) >= $this->capacity;
    }
}
