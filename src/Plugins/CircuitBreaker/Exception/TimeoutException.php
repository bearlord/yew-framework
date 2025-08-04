<?php

namespace Yew\Plugins\CircuitBreaker\Exception;

class TimeoutException extends CircuitBreakerException
{
    public function __construct(string $message, int $code, $result)
    {
        parent::__construct($message, $code);
        $this->result = $result;
    }
}
