<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Database;

use Yew\Core\Pool\Config;
use Yew\Core\Pool\ConnectionInterface;
use Yew\Core\Pool\DefaultFrequency;
use Yew\Core\Pool\Pool;
use Yew\Coroutine\Server\Server;

class DatabasePool extends Pool
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
     * @throws \Yew\Framework\Db\Exception
     * @throws \Yew\Framework\Exception\InvalidConfigException
     */
    protected function createConnection(): ConnectionInterface
    {
        $connection = new PoolConnection($this, $this->getConfig());
        $connection->connect();

        return $connection;
    }


    /**
     * @return \Yew\Framework\Db\Connection
     * @throws \Exception|\Throwable
     */
    public function db(): ?\Yew\Framework\Db\Connection
    {
        $contextKey = sprintf("db:%s", $this->getConfig()->getName());

        $db = getContextValue($contextKey);
        if ($db == null) {
            /** @var \Yew\Plugins\Database\PoolConnection $poolConnection */
            $poolConnection = $this->get();
            if (empty($poolConnection)) {
                $errorMessage = "Connection pool {$contextKey} exhausted, Cannot establish new connection, please increase maxConnections";
                Server::$instance->getLog()->error($errorMessage);
                throw new \RuntimeException($errorMessage);
            }

            $db = $poolConnection->getDbConnection();

            \Swoole\Coroutine::defer(function () use ($poolConnection, $contextKey) {
                $db = getContextValue($contextKey);

                $poolConnection->setLastUseTime(microtime(true));
                $poolConnection->setConnection($db);

                $this->release($poolConnection);
            });
            setContextValue($contextKey, $db);
        }

        if (!$db instanceof \Yew\Framework\Db\Connection) {
            $errorMessage = "Connection pool {$contextKey} exhausted, Cannot establish new connection, please increase maxConnections";
            Server::$instance->getLog()->error($errorMessage);
            throw new \RuntimeException($errorMessage);
        }

        return $db;
    }

}
