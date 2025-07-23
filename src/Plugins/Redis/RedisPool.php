<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Redis;

use Yew\Core\Pool\ConnectionInterface;
use Yew\Core\Pool\DefaultFrequency;
use Yew\Core\Pool\Exception\ConnectionException;
use Yew\Core\Pool\Pool;
use Yew\Coroutine\Server\Server;

class RedisPool extends Pool
{
    /**
     * @param Config $config
     * @throws \ReflectionException
     */
    public function __construct(Config $config)
    {
        parent::__construct($config);

        $this->frequency = new DefaultFrequency($this);
    }

    /**
     * @return ConnectionInterface
     * @throws ConnectionException
     * @throws Exception\RedisException
     * @throws \RedisException
     */
    protected function createConnection(): ConnectionInterface
    {
        $connection = new PoolConnection($this, $this->getConfig());
        $connection->connect();

        return $connection;
    }

    /**
     * @return RedisConnection|null
     * @throws ConnectionException
     * @throws \RedisClusterException
     * @throws \RedisException
     * @throws \Throwable
     */
    public function db(): ?RedisConnection
    {
        $contextKey = sprintf("Redis:%s", $this->getConfig()->getName());

        $db = getContextValue($contextKey);
        if ($db == null) {
            /** @var PoolConnection $poolConnection */
            $poolConnection = $this->get();
            if (empty($poolConnection)) {
                $errorMessage = "Connection pool {$contextKey} exhausted, Cannot establish new connection, please increase maxConnections";
                Server::$instance->getLog()->error($errorMessage);
                throw new \RuntimeException($errorMessage);
            }

            /** @var \Redis $db */
            $db = $poolConnection->getDbConnection();

            \Swoole\Coroutine::defer(function () use ($poolConnection, $contextKey) {
                $db = getContextValue($contextKey);

                $poolConnection->setLastUseTime(microtime(true));
                $poolConnection->setConnection($db);

                $this->release($poolConnection);
            });
            setContextValue($contextKey, $db);
        }

        if (!$db instanceof RedisConnection) {
            $errorMessage = "Connection pool {$contextKey} exhausted, Cannot establish new connection, please increase maxConnections";
            Server::$instance->getLog()->error($errorMessage);
            throw new \RuntimeException($errorMessage);
        }

        return $db;
    }
}
