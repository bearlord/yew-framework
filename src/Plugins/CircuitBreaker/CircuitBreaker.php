<?php

namespace Yew\Plugins\CircuitBreaker;
use Yew\Yew;

class CircuitBreaker implements CircuitBreakerInterface
{
    protected string $name;

    protected State $state;

    protected float $timestamp;

    /**
     * Failure count.
     */
    protected int $failCounter;

    /**
     * Success count.
     */
    protected int $successCounter;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->state = Yew::getContainer()->get(State::class);
        $this->init();
    }

    /**
     * @return State
     */
    public function state(): State
    {
        return $this->state;
    }

    /**
     * @return bool
     */
    public function attempt(): bool
    {
        return Yew::getContainer()->get(Attempt::class)->attempt();
    }

    /**
     * @return void
     */
    public function open(): void
    {
        $this->init();
        $this->state->open();
    }

    /**
     * @return void
     */
    public function close(): void
    {
        $this->init();
        $this->state->close();
    }

    /**
     * @return void
     */
    public function halfOpen(): void
    {
        $this->init();
        $this->state->halfOpen();
    }

    /**
     * @return float
     */
    public function getDuration(): float
    {
        return microtime(true) - $this->timestamp;
    }

    /**
     * @return int
     */
    public function getFailCounter(): int
    {
        return $this->failCounter;
    }

    /**
     * @return int
     */
    public function getSuccessCounter(): int
    {
        return $this->successCounter;
    }

    /**
     * @return float
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * @return int
     */
    public function incrFailCounter(): int
    {
        return ++$this->failCounter;
    }

    /**
     * @return int
     */
    public function incrSuccessCounter(): int
    {
        return ++$this->successCounter;
    }

    /**
     * @return void
     */
    protected function init()
    {
        $this->timestamp = microtime(true);
        $this->failCounter = 0;
        $this->successCounter = 0;
    }
}