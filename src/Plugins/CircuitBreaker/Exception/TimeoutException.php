<?php

namespace Yew\Plugins\CircuitBreaker\Exception;

class TimeoutException extends CircuitBreakerException
{
    public function __construct(string $message, $result)
    {
        parent::__construct($message);
        $this->result = $result;
    }
}
