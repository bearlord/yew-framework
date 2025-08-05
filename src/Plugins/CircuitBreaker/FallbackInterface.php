<?php

namespace Yew\Plugins\CircuitBreaker;

use Yew\Goaop\Aop\Intercept\MethodInvocation;

interface FallbackInterface
{
    public function fallback(MethodInvocation $invocation);
}
