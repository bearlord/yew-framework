<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;


use Yew\Plugins\Redis\GetRedis;

class RedisSessionStorage implements SessionStorage
{
    use GetRedis;
    /**
     * @var SessionConfig
     */
    private SessionConfig $sessionConfig;

    /**
     * @var array
     */
    private array $redisConfig;

    const prefix = "SESSION_";

    /**
     * RedisSessionStorage constructor.
     * @param SessionConfig $sessionConfig
     */
    public function __construct(SessionConfig $sessionConfig)
    {
        $this->sessionConfig = $sessionConfig;
    }

    /**
     * @param string $id
     * @return mixed
     * @throws \Throwable
     */
    public function get(string $id)
    {
        $redis = $this->redis($this->sessionConfig->getRedisName());
        $redis->select($this->sessionConfig->getDatabase());
        return $redis->get(self::prefix . $id);
    }

    /**
     * @param string $id
     * @param string $data
     * @return mixed
     * @throws \Throwable
     */
    public function set(string $id, string $data)
    {
        $redis = $this->redis($this->sessionConfig->getRedisName());
        $redis->select($this->sessionConfig->getDatabase());
        return $redis->setex(self::prefix . $id, $this->sessionConfig->getTimeout(), $data);
    }

    /**
     * @param string $id
     * @return mixed
     * @throws \Throwable
     */
    public function remove(string $id)
    {
        $redis = $this->redis($this->sessionConfig->getRedisName());
        $redis->select($this->sessionConfig->getDatabase());
        return $redis->del(self::prefix . $id);
    }
}