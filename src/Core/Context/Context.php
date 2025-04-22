<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Context;

class Context
{
    const storageKey = "@context";

    /**
     * @var array
     */
    private array $contain = [];

    /**
     * @var array
     */
    private array $classContain = [];

    /**
     * @var Context|null
     */
    private ?Context $parentContext;

    /**
     * Context constructor.
     *
     * @param Context|null $parentContext
     */
    public function __construct(?Context $parentContext = null)
    {
        $this->parentContext = $parentContext;
    }

    /**
     * Add
     *
     * @param string $name
     * @param $value
     */
    public function add(string $name, $value)
    {
        if ($value == null) {
            return;
        }
        $this->contain[$name] = $value;
        if (!is_string($value) && !is_int($value) && !is_bool($value) && !is_float($value) && !is_double($value) && !is_array($value) && !is_callable($value) && !is_long($value)) {
            $this->classContain[get_class($value)] = $value;
        }
    }

    /**
     * Add with class
     *
     * @param string $name
     * @param $value
     * @param $class
     */
    public function addWithClass(string $name, $value, $class)
    {
        if ($value == null) {
            return;
        }
        $this->contain[$name] = $value;

        if (class_exists($class)) {
            $this->classContain[$class] = $value;
        } else {
            if (!is_string($value) && !is_int($value) && !is_bool($value) && !is_float($value) && !is_double($value) && !is_array($value) && !is_callable($value) && !is_long($value)) {
                $this->classContain[get_class($value)] = $value;
            }
        }
    }

    /**
     * Get by class name
     *
     * @param string $className
     * @return mixed|null
     */
    public function getByClassName(string $className)
    {
        return $this->classContain[$className] ?? null;
    }

    /**
     * Get deep by class name
     *
     * @param string $className
     * @return mixed|null
     */
    public function getDeepByClassName(string $className)
    {
        $result = $this->classContain[$className] ?? null;
        if ($result == null && $this->parentContext != null) {
            return $this->parentContext->getDeepByClassName($className);
        }
        return $result;
    }

    /**
     * Get
     * @param string $name
     * @return mixed|null
     */
    public function get(string $name)
    {
        return $this->contain[$name] ?? null;
    }

    /**
     * Get deep
     *
     * @param string $name
     * @return null
     */
    public function getDeep(string $name)
    {
        $result = $this->contain[$name] ?? null;
        if ($result == null && $this->parentContext != null) {
            return $this->parentContext->getDeep($name);
        }
        return $result;
    }

    /**
     * @param Context|null $parentContext
     */
    public function setParentContext(?Context $parentContext): void
    {
        if ($parentContext === $this) {
            return;
        }
        $this->parentContext = $parentContext;
    }

    /**
     * @return Context|null
     */
    public function getParentContext(): ?Context
    {
        return $this->parentContext;
    }

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return array_keys($this->contain);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        if (array_key_exists($key, array_keys($this->contain))) {
            unset($this->contain[$key]);
        }
        return true;
    }
}
