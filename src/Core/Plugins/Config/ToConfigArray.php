<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Config;

use ReflectionClass;
use ReflectionException;

trait ToConfigArray
{
    /**
     * @var ReflectionClass|null
     */
    protected ?ReflectionClass $reflectionClass = null;

    /**
     * To config array
     *
     * @throws ReflectionException
     */
    public function toConfigArray(): array
    {
        $config = [];
        if ($this->reflectionClass == null) {
            $this->reflectionClass = new ReflectionClass(static::class);
        }

        foreach ($this->reflectionClass->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() != BaseConfig::class) {
                $varName = $property->getName();
                if ($property->isPrivate()) {
                    continue;
                }

                if ($this->$varName !== null) {
                    if (is_array($this->$varName)) {
                        foreach ($this->$varName as $key => $value) {
                            if ($value instanceof BaseConfig) {
                                $config[$this->toUnderlineCase($varName)][$this->toUnderlineCase($key)] = $value->toConfigArray();
                            } else {
                                $config[$this->toUnderlineCase($varName)][$this->toUnderlineCase($key)] = $value;
                            }
                        }
                    } elseif ($this->$varName instanceof BaseConfig) {
                        $config[$this->toUnderlineCase($varName)] = $this->$varName->toConfigArray();
                    } else {
                        $config[$this->toUnderlineCase($varName)] = $this->$varName;
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Camel case to underline case
     *
     * @param string $var
     * @return float|int|string
     */
    protected function toUnderlineCase(string $var): string
    {
        if (is_numeric($var)) {
            return $var;
        }

        $result = "";
        for ($i = 0; $i < strlen($var); $i++) {
            $str = ord($var[$i]);
            if ($str > 64 && $str < 91) {
                $result .= "_" . strtolower($var[$i]);
            } else {
                $result .= $var[$i];
            }
        }

        return $result;
    }

    /**
     * Build from config
     *
     * @param array|null $config
     * @return static
     */
    public function buildFromConfig(?array $config = null): self
    {
        if ($config == null) {
            return $this;
        }

        foreach ($config as $key => $value) {
            $varName = $this->toCamelCase($key);
            $func = "set" . ucfirst($varName);
            if (is_callable([$this, $func])) {
                call_user_func([$this, $func], $value);
            }
        }

        return $this;
    }

    /**
     * Underline to Camel Case
     *
     * @param string $var
     * @return string
     */
    protected function toCamelCase(string $var): string
    {
        if (is_numeric($var)) {
            return $var;
        }

        $result = "";
        for ($i = 0; $i < strlen($var); $i++) {
            if ($var[$i] == "_") {
                $i = $i + 1;
                $result .= strtoupper($var[$i]);
            } else {
                $result .= $var[$i];
            }
        }

        return $result;
    }
}
