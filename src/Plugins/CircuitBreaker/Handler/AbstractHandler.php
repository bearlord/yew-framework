<?php

namespace Yew\Plugins\CircuitBreaker\Handler;

use Yew\Core\Log\LoggerInterface;
use Yew\Coroutine\Server\Server;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Plugins\CircuitBreaker\CircuitBreaker;
use Yew\Plugins\CircuitBreaker\CircuitBreakerFactory;
use Yew\Plugins\CircuitBreaker\CircuitBreakerInterface;
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker as Annotation;
use Yew\Yew;

abstract class AbstractHandler implements HandlerInterface
{
    protected CircuitBreakerFactory $factory;

    protected ?LoggerInterface $logger = null;

    /**
     * @throws \ReflectionException
     * @throws \Yew\Framework\Di\NotInstantiableException
     * @throws \Yew\Framework\Exception\InvalidConfigException
     */
    public function __construct()
    {
        $this->factory = Yew::getContainer()->get(CircuitBreakerFactory::class);

        $this->logger = Server::$instance->getLog();
    }

    /**
     * @param MethodInvocation $invocation
     * @param Annotation $annotation
     * @return mixed
     * @throws \ReflectionException
     * @throws \Yew\Framework\Di\NotInstantiableException
     * @throws \Yew\Framework\Exception\InvalidConfigException
     */
    public function handle($routeMethodName, MethodInvocation $invocation, Annotation $annotation)
    {
        /** @var CircuitBreakerInterface $breaker */
        $breaker = $this->factory->get($routeMethodName);
        if (!$breaker instanceof CircuitBreakerInterface) {
            $breaker = Yew::getContainer()->get(CircuitBreaker::class, [$routeMethodName]);
            $this->factory->set($routeMethodName, $breaker);
        }

        $state = $breaker->state();

        if ($state->isOpen()) {
            $this->switch($breaker, $invocation, false);
            return $this->fallback($invocation, $breaker, $annotation);
        }
        if ($state->isHalfOpen()) {
            return $this->attemptCall($routeMethodName, $invocation, $breaker, $annotation);
        }

        return $this->call($routeMethodName, $invocation, $breaker, $annotation);
    }

    /**
     * @param CircuitBreakerInterface $breaker
     * @param Annotation $annotation
     * @param bool $status
     * @return void
     */
    protected function switch(CircuitBreakerInterface $breaker, Annotation $annotation, bool $status): void
    {
        $state = $breaker->state();
        if ($state->isClose()) {
            $this->logger->debug('The current state is closed.');
            if ($breaker->getDuration() >= $annotation->duration) {
                $info = sprintf(
                    'The duration=%ss of closed state longer than the annotation duration=%ss and is reset to the closed state.',
                    $breaker->getDuration(),
                    $annotation->duration
                );
                $this->logger->debug($info);
                $breaker->close();
                return;
            }

            if (!$status && $breaker->getFailCounter() >= $annotation->failCounter) {
                $info = sprintf(
                    'The failCounter=%s more than the annotation failCounter=%s and is reset to the open state.',
                    $breaker->getFailCounter(),
                    $annotation->failCounter
                );
                $this->logger->debug($info);
                $breaker->open();
                return;
            }

            return;
        }

        if ($state->isHalfOpen()) {
            $this->logger->debug('The current state is half opened.');
            if (!$status && $breaker->getFailCounter() >= $annotation->failCounter) {
                $info = sprintf(
                    'The failCounter=%s more than the annotation failCounter=%s and is reset to the open state.',
                    $breaker->getFailCounter(),
                    $annotation->failCounter
                );
                $this->logger->debug($info);
                $breaker->open();
                return;
            }

            if ($status && $breaker->getSuccessCounter() >= $annotation->successCounter) {
                $info = sprintf(
                    'The successCounter=%s more than the annotation successCounter=%s and is reset to the closed state.',
                    $breaker->getSuccessCounter(),
                    $annotation->successCounter
                );
                $this->logger->debug($info);
                $breaker->close();
                return;
            }

            return;
        }

        if ($state->isOpen()) {
            $this->logger->debug('The current state is opened.');
            if ($breaker->getDuration() >= $annotation->duration) {
                $info = sprintf(
                    'The duration=%ss of opened state longer than the annotation duration=%ss and is reset to the half opened state.',
                    $breaker->getDuration(),
                    $annotation->duration
                );
                $this->logger->debug($info);
                $breaker->halfOpen();
            }
        }
    }

    /**
     * @param ProceedingJoinPoint $invocation
     * @param CircuitBreakerInterface $breaker
     * @param Annotation $annotation
     * @return mixed
     */
    protected function call(string $routeMethodName, $invocation, CircuitBreakerInterface $breaker, Annotation $annotation)
    {
        try {
            $result = $this->process($routeMethodName, $invocation, $breaker, $annotation);

            $breaker->incrSuccessCounter();
            $this->switch($breaker, $annotation, true);
        } catch (Throwable $exception) {
            if (!$exception instanceof CircuitBreakerException) {
                throw $exception;
            }

            $result = $exception->getResult();
            $msg = sprintf('%s %s.', $routeMethodName, $exception->getMessage());
            $this->logger->debug($msg);

            $breaker->incrFailCounter();
            $this->switch($breaker, $annotation, false);
        }

        return $result;
    }

    protected function attemptCall(string $routeMethodName, $invocation, CircuitBreakerInterface $breaker, Annotation $annotation)
    {
        if ($breaker->attempt()) {
            return $this->call($routeMethodName, $invocation, $breaker, $annotation);
        }

        return $this->fallback($invocation, $breaker, $annotation);
    }

    protected function fallback(MethodInvocation $invocation, CircuitBreakerInterface $breaker, Annotation $annotation)
    {
        if ($annotation->fallback instanceof Closure) {
            return ($annotation->fallback)($invocation);
        }
        [$class, $method] = $this->prepareHandler($annotation->fallback, $invocation);

        $instance = $this->container->get($class);
        if ($instance instanceof FallbackInterface) {
            return $instance->fallback($invocation);
        }

        $arguments = $invocation->getArguments();

        return $instance->{$method}(...$arguments);
    }

    abstract protected function process(string $routeMethodName, MethodInvocation $invocation, $breaker, $annotation);

    protected function prepareHandler($fallback, ProceedingJoinPoint $invocation): array
    {
        if (is_string($fallback)) {
            $fallback = explode('::', $fallback);
            if (!isset($fallback[1]) && method_exists($invocation->className, $fallback[0])) {
                return [$invocation->className, $fallback[0]];
            }
            $fallback[1] ??= 'fallback';
        }

        if (is_array($fallback) && count($fallback) === 2) {
            return $fallback;
        }

        throw new BadFallbackException('The fallback is invalid.');
    }
}
