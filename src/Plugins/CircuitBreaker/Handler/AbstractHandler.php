<?php

namespace Yew\Plugins\CircuitBreaker\Handler;

use Yew\Core\Log\LoggerInterface;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Di\Container;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Plugins\CircuitBreaker\CircuitBreaker;
use Yew\Plugins\CircuitBreaker\CircuitBreakerFactory;
use Yew\Plugins\CircuitBreaker\CircuitBreakerInterface;
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker as Annotation;
use Yew\Plugins\CircuitBreaker\Exception\BadFallbackException;
use Yew\Plugins\CircuitBreaker\Exception\CircuitBreakerException;
use Yew\Yew;

abstract class AbstractHandler implements HandlerInterface
{
    public ?Container $container = null;

    protected CircuitBreakerFactory $factory;

    protected ?LoggerInterface $logger = null;

    /**
     * @throws \ReflectionException
     * @throws \Yew\Framework\Di\NotInstantiableException
     * @throws \Yew\Framework\Exception\InvalidConfigException
     */
    public function __construct()
    {
        $this->container = Yew::getContainer();

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
            $breaker = $this->container->get(CircuitBreaker::class, [$routeMethodName]);
            $this->factory->set($routeMethodName, $breaker);
        }

        $state = $breaker->state();

        if ($state->isOpen()) {
            $this->switch($breaker, $annotation, false);
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
     * @param string $routeMethodName
     * @param MethodInvocation $invocation
     * @param CircuitBreakerInterface $breaker
     * @param Annotation $annotation
     * @return mixed
     */
    protected function call(string $routeMethodName, MethodInvocation $invocation, CircuitBreakerInterface $breaker, Annotation $annotation)
    {
        try {
            $result = $this->process($routeMethodName, $invocation, $breaker, $annotation);

            $breaker->incrSuccessCounter();
            $this->switch($breaker, $annotation, true);
        } catch (\Exception $exception) {
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

    /**
     * @param string $routeMethodName
     * @param MethodInvocation $invocation
     * @param CircuitBreakerInterface $breaker
     * @param Annotation $annotation
     * @return mixed
     */
    protected function attemptCall(string $routeMethodName, MethodInvocation $invocation, CircuitBreakerInterface $breaker, Annotation $annotation)
    {
        if ($breaker->attempt()) {
            return $this->call($routeMethodName, $invocation, $breaker, $annotation);
        }

        return $this->fallback($invocation, $breaker, $annotation);
    }

    /**
     * @param MethodInvocation $invocation
     * @param CircuitBreakerInterface $breaker
     * @param Annotation $annotation
     * @return mixed
     */
    protected function fallback(MethodInvocation $invocation, CircuitBreakerInterface $breaker, Annotation $annotation)
    {
        [$class, $method] = $this->prepareHandler($annotation->fallback);

        $instance = $this->container->get($class);
        if ($instance instanceof FallbackInterfac) {
            return $instance->fallback($invocation);
        }

        return $instance->{$method}();
    }

    abstract protected function process(string $routeMethodName, MethodInvocation $invocation, CircuitBreakerInterface $breaker, Annotation $annotation);

    /**
     * @param $fallback
     * @return array
     */
    protected function prepareHandler($fallback): array
    {
        if (is_string($fallback)) {
            $fallback = explode('::', $fallback);
        }

        if (is_array($fallback)
            && count($fallback) === 2
            && class_exists($fallback[0])
            && method_exists($fallback[0], $fallback[1])
        ) {
            return $fallback;
        }

        throw new BadFallbackException('The fallback is invalid.');
    }
}
