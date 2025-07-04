<?php

namespace Yew\Framework\Config;

use ArrayAccess;
use Yew\Framework\Helpers\ArrayHelper;

class Config implements ConfigInterface
{
    /**
     * @var array
     */
    protected array $configuration = [];

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->configuration = $config;
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @param array $configuration
     * @return void
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed
     * @throws \Exception
     */
    public function get(string $key, $default = null)
    {
        return ArrayHelper::getValue($this->configuration, $key, $default);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return ArrayHelper::issetNested($this->configuration, $key);
    }

    /**
     * @param string $key
     * @param $value
     * @return void
     */
    public function set(string $key, $value): void
    {
        ArrayHelper::setValue($this->configuration, $key, $value);
    }

    /**
     * @param array $values
     * @return void
     */
    public function setMultiple(array $values): void
    {
        if (empty($values)) {
            return;
        }

        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function addMultiple(array $values)
    {
        $this->configuration = array_replace_recursive($this->configuration, $values);
    }
}