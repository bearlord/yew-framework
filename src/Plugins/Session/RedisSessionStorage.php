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

    private SessionConfig $sessionConfig;

    private const prefix = "SESSION_";

    public function __construct(SessionConfig $sessionConfig)
    {
        $this->sessionConfig = $sessionConfig;
    }

    public function get(string $id): ?string
    {
        $redis = $this->redis($this->sessionConfig->getRedisName());
        $redis->select($this->sessionConfig->getDatabase());
        $value = $redis->get(self::prefix . $id);
        return $value === false ? null : $value;
    }

    public function set(string $id, string $data): void
    {
        $redis = $this->redis($this->sessionConfig->getRedisName());
        $redis->select($this->sessionConfig->getDatabase());
        $redis->setex(self::prefix . $id, $this->sessionConfig->getTimeout(), $data);
    }

    public function remove(string $id): void
    {
        $redis = $this->redis($this->sessionConfig->getRedisName());
        $redis->select($this->sessionConfig->getDatabase());
        $redis->del(self::prefix . $id);
    }
}
