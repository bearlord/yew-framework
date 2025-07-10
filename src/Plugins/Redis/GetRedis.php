<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Redis;

use Yew\Coroutine\Server\Server;

trait GetRedis
{
    /**
     * @param string|null $name
     * @return mixed|RedisConnection
     * @throws \Throwable
     */
    public function redis(?string $name = "default")
    {
        $db = getContextValue("Redis:$name");

        //Default database number
        $defaultDbNum = Server::$instance->getConfigContext()->get("yew.redis.{$name}.database");

        if ($db == null) {
            /** @var RedisPools $redisPools */
            $redisPools = getDeepContextValueByClassName(RedisPools::class);
            if (!empty($redisPools)) {
                $pool = $redisPools->getPool($name);

                if ($pool == null) {
                    throw new \RuntimeException("Redis connection pool named {$name} not found");
                }

                try {
                    $db = $pool->db();
                    if (empty($db)) {
                        throw new \RuntimeException("Redis connection fetch failed");
                    }
                    return $db;
                } catch (\Exception $e) {
                    Server::$instance->getLog()->error($e);
                }
            }
        }

        if (!empty($db)) {
            //Be sure to select default database
            if ($db->getDbNum() != $defaultDbNum) {
                $db->select($defaultDbNum);
            }
        }
        
        return $db;
    }
}
