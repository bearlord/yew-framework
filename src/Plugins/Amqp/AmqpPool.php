<?php
/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Amqp;

use Yew\Core\Channel\Channel;
use Yew\Core\Context\Context;
use Yew\Core\Pool\ConnectionInterface;
use Yew\Core\Pool\DefaultFrequency;
use Yew\Core\Pool\Pool;
use Yew\Coroutine\Coroutine;
use PhpAmqpLib\Connection\AbstractConnection;

class AmqpPool extends Pool
{
    /**
     * @param Config $config
     * @throws \Exception
     */
    public function __construct(Config $config)
    {
        parent::__construct($config);
    }

    /**
     * @return ConnectionInterface
     */
    protected function createConnection(): ConnectionInterface
    {
        $connection = new PoolConnection($this, $this->getConfig());
        $connection->connect();

        return $connection;
    }

    /**
     * @return AbstractConnection
     */
    public function db()
    {
        $contextKey = sprintf("Amqp:%s", $this->getConfig()->getName());
        $db = getContextValue($contextKey);

        if ($db == null) {
            /** @var AbstractConnection $db */
            $db = $this->pool->pop();

            \Swoole\Coroutine::defer(function () use ($db) {
                $this->pool->push($db);
            });
            setContextValue($contextKey, $db);
        }
        return $db;
    }
}