<?php
/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>, bearload <565364226@qq.com>
 */

namespace Yew\Plugins\Amqp;

/**
 * Class Configs
 * @package Yew\Plugins\Amqp
 */
class Configs
{
    /**
     * @var array
     */
    protected $configs;

    /**
     * @return array
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * @param array $configs
     */
    public function setConfigs(array $configs): void
    {
        $this->configs = $configs;
    }

    /**
     * @param Config $buildFromConfig
     */
    public function addConfig(Config $buildFromConfig)
    {
        $this->configs[$buildFromConfig->getName()] = $buildFromConfig;
    }
}