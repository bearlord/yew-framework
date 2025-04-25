<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Redis;

/**
 * Class RedisProxy
 * @package Yew\Plugins\Redis
 */
class RedisProxy
{
    use GetRedis;

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->redis()->$name;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->redis()->$name = $value;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->redis(), $name], $arguments);
    }
}