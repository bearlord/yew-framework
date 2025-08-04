<?php

namespace Yew\Plugins\CircuitBreaker;

class Attempt
{
    public function attempt(): bool
    {
        return rand(0, 100) >= 50;
    }
}
