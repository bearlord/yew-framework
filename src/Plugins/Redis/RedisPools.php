<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Redis;

use Yew\Core\Pool\Exception\ConnectionException;
use Yew\Core\Pool\Pools;
use Yew\Plugins\Redis\Exception\RedisException;

class RedisPools extends Pools
{
    /**
     * @return RedisConnection
     * @throws ConnectionException
     * @throws RedisException
     * @throws \RedisClusterException
     * @throws \RedisException
     * @throws \Throwable
     */
    public function db(): RedisConnection
    {
        $pool = $this->getPool();
        if ($pool == null) {
            throw new RedisException("No default redis configuration is set");
        }

        return $pool->db();
    }
}
