<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Config;

interface ConfigInterface
{
    public function getConfiguration(): array;

    

    /**
     * @param string $key
     * @param $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param string $keys
     * @return bool
     */
    public function has(string $keys): bool;

    /**
     * @param string $key
     * @param $value
     * @return mixed
     */
    public function set(string $key, $value);
}