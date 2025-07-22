<?php

namespace Yew\Plugins\Amqp;

use Yew\Core\Pool\ConfigInterface;
use Yew\Core\Pool\Connection as CorePoolConnection;
use Yew\Core\Pool\Exception\ConnectionException;

class PoolConnection  extends CorePoolConnection
{
    protected $connection = null;

    /**
     * @param AmqpPool $pool
     * @param ConfigInterface $config
     */
    public function __construct(AmqpPool $pool, ConfigInterface $config)
    {
        parent::__construct($pool, $config);
    }
    
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param null $connection
     */
    public function setConnection($connection): void
    {
        $this->connection = $connection;
    }

    public function connect(): bool
    {
        $connectionHandle = new Connection($config);

        $this->setConnection($connectionHandle);
        return true;
    }


    public function getActiveConnection()
    {
        if ($this->check()) {
            return $this;
        }

        if (!$this->reconnect()) {
            throw new ConnectionException('Amqp connection reconnect failed.');
        }

        return $this;
    }

    public function reconnect(): bool
    {
        // TODO: Implement reconnect() method.
    }

    public function close(): bool
    {
        // TODO: Implement close() method.
    }


}