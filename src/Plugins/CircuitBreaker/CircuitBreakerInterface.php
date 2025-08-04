<?php

namespace Yew\Plugins\CircuitBreaker;

interface CircuitBreakerInterface
{
    /**
     * @return State
     */
    public function state(): State;

    /**
     * @return bool
     */
    public function attempt(): bool;

    /**
     * @return void
     */
    public function open(): void;

    /**
     * @return void
     */
    public function close(): void;

    /**
     * @return void
     */
    public function halfOpen(): void;

    /**
     * @return float
     */
    public function getDuration(): float;

    /**
     * @return int
     */
    public function getFailCounter(): int;

    /**
     * @return int
     */
    public function getSuccessCounter(): int;

    /**
     * @return int
     */
    public function incrSuccessCounter(): int;

    /**
     * @return int
     */
    public function incrFailCounter(): int;
}
