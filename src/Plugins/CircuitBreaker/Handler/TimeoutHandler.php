<?php

namespace Yew\Plugins\CircuitBreaker\Handler;


use Yew\Coroutine\Server\Server;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker as Annotation;
use Yew\Plugins\CircuitBreaker\CircuitBreakerInterface;
use Yew\Plugins\CircuitBreaker\Exception\TimeoutException;

class TimeoutHandler extends AbstractHandler
{
    public const DEFAULT_TIMEOUT = 5;

    /**
     * @param string $routeMethodName
     * @param MethodInvocation $invocation
     * @param CircuitBreakerInterface $breaker
     * @param Annotation $annotation
     * @return mixed
     */
    protected function process(string $routeMethodName, MethodInvocation $invocation, CircuitBreakerInterface $breaker, Annotation $annotation)
    {
        $timeout = $annotation->options['timeout'] ?? self::DEFAULT_TIMEOUT;

        $markStartTime = microtime(true);

        $result = $invocation->proceed();

        $useTime = microtime(true) - $markStartTime;
        
        if ($useTime > $timeout) {
            if (Server::$instance->getServerConfig()->isDebug()) {
                throw new TimeoutException('timeout, use ' . $useTime . ' s', 80504, $result);
            }
        }

        /*
        $msg = sprintf('%s success, use %ss.', $routeMethodName, $useTime);
        $this->logger->debug($msg);
        */

        return $result;
    }
}
