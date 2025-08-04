<?php

namespace Yew\Plugins\CircuitBreaker;

class CircuitBreakerFactory
{
    /**
     * @var CircuitBreakerInterface[]
     */
    protected array $breakers = [];

    /**
     * @param string $name
     * @return CircuitBreakerInterface|null
     */
    public function get(string $name): ?CircuitBreakerInterface
    {
        return $this->breakers[$name] ?? null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->breakers[$name]);
    }

    /**
     * @param string $name
     * @param CircuitBreakerInterface $storage
     * @return CircuitBreakerInterface
     */
    public function set(string $name, CircuitBreakerInterface $storage): CircuitBreakerInterface
    {
        return $this->breakers[$name] = $storage;
    }
}
