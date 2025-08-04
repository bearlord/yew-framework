<?php

namespace Yew\Plugins\CircuitBreaker\Handler;

use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker;
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker as Annotation;

interface HandlerInterface
{
    public function handle($routeMethodName, MethodInvocation $invocation, Annotation $annotation);
}
