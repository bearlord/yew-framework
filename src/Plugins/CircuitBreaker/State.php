<?php

namespace Yew\Plugins\CircuitBreaker;

class State
{
    public const CLOSE = 0;

    public const HALF_OPEN = 1;

    public const OPEN = 2;

    protected int $state;

    public function __construct()
    {
        $this->state = self::CLOSE;
    }

    /**
     * @return void
     */
    public function open()
    {
        $this->state = self::OPEN;
    }

    /**
     * @return void
     */
    public function close()
    {
        $this->state = self::CLOSE;
    }

    /**
     * @return void
     */
    public function halfOpen()
    {
        $this->state = self::HALF_OPEN;
    }

    /**
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->state === self::OPEN;
    }

    /**
     * @return bool
     */
    public function isClose(): bool
    {
        return $this->state === self::CLOSE;
    }

    /**
     * @return bool
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::HALF_OPEN;
    }
}
