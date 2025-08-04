<?php

namespace Yew\Plugins\CircuitBreaker\Exception;

use RuntimeException;

class CircuitBreakerException extends RuntimeException
{
    public $result = null;

    public function setResult($result)
    {
        $this->result = $result;
        return $this;
    }

    public function getResult()
    {
        return $this->result;
    }
}
