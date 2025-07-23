<?php

namespace Yew\Plugins\Amqp;

use Yew\Core\Pool\ConfigInterface;
use Yew\Core\Pool\Connection as CorePoolConnection;
use Yew\Core\Pool\Exception\ConnectionException;

class PoolConnection  extends CorePoolConnection
{
    protected ?AmqpConnection $connection = null;

    /**
     * @param AmqpPool $pool
     * @param ConfigInterface $config
     */
    public function __construct(AmqpPool $pool, ConfigInterface $config)
    {
        parent::__construct($pool, $config);
    }

    /**
     * @return AmqpConnection|null
     */
    public function getConnection(): ?AmqpConnection
    {
        return $this->connection;
    }

    /**
     * @param AmqpConnection|null $connection
     * @return void
     */
    public function setConnection(?AmqpConnection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return bool
     */
    public function connect(): bool
    {
        $connectionHandle = new AmqpConnection($this->config);

        $this->setConnection($connectionHandle);
        return true;
    }

    /**
     * @return $this
     * @throws ConnectionException
     */
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

    /**
     * @return bool
     */
    public function reconnect(): bool
    {
        $this->close();
        $this->connect();
        return true;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        if ($this->connection instanceof AmqpConnection) {
            $this->connection->close();
        }

        unset($this->connection);

        return true;
    }

    /**
     * @return RedisConnection
     * @throws ConnectionException
     * @throws \RedisException
     */
    public function getDbConnection(): AmqpConnection
    {
        try {
            $activeConnection = $this->getActiveConnection();
            return $activeConnection->getConnection();
        } catch (\Exception $exception) {
            Server::$instance->getLog()->warning('Get connection failed, try again. ' . $exception);

            $activeConnection = $this->getActiveConnection();
            return $activeConnection->getConnection();
        }
    }


}