<?php

namespace Yew\Framework\Config;

use Yew\Framework\Helpers\ArrayHelper;

class Config implements ConfigInterface
{
    /**
     * @var array
     */
    protected array $config = [];

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed
     * @throws \Exception
     */
    public function get(string $key, $default = null)
    {
        return ArrayHelper::getValue($this->config, $key, $default);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return ArrayHelper::issetNested($this->config, $key);
    }

    /**
     * @param string $key
     * @param $value
     * @return void
     */
    public function set(string $key, $value)
    {
        ArrayHelper::setValue($this->config, $key, $value);
    }


}