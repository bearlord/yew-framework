<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Mongodb;

use Swoole\RemoteObject\Client;
use Yew\Core\Pool\ConfigInterface;
use Yew\Core\Pool\Connection as CorePoolConnection;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Mongodb\Connection;

/**
 * @property MongodbPool $pool
 * @property Config $config
 */
class PoolConnection extends CorePoolConnection
{
    /**
     * In local mode this is a real Connection;
     * in RemoteObject mode it is a Swoole\RemoteObject\RemoteObject proxy.
     *
     * @var Connection|mixed|null
     */
    protected mixed $connection = null;

    /**
     * @var Client|null
     */
    protected ?Client $client = null;

    /**
     * @param MongodbPool $pool
     * @param ConfigInterface $config
     */
    public function __construct(MongodbPool $pool, ConfigInterface $config)
    {
        parent::__construct($pool, $config);
    }

    /**
     * @return Connection|mixed|null
     */
    public function getConnection(): mixed
    {
        return $this->connection;
    }

    /**
     * @param Connection|mixed $connection
     * @return void
     */
    public function setConnection(mixed $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return Client|null
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @return bool
     * @throws \Throwable
     */
    public function connect(): bool
    {
        /** @var Config $config */
        $config = $this->config;

        if ($config->isRemoteObjectEnable()) {
            $options = [];
            if ($config->getRemoteObjectApiKey() !== "") {
                $options['api_key'] = $config->getRemoteObjectApiKey();
            }

            $this->client = new Client($config->getRemoteObjectHost(), $config->getRemoteObjectPort(), $options);
            $this->connection = $this->client->create(
                $config->getRemoteObjectClass(),
                ...$this->buildRemoteArgs($config)
            );
        } else {
            $connectionHandle = new Connection($this->buildConnectionConfig($config));
            $connectionHandle->open();
            $this->connection = $connectionHandle;
        }

        $this->setLastUseTime(microtime(true));

        return true;
    }

    /**
     * @return bool
     * @throws \Throwable
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
        if ($this->connection instanceof Connection) {
            $this->connection->close();
        }

        // The RemoteObject proxy destroys the remote object on the server when destructed.
        $this->connection = null;
        $this->client = null;

        return true;
    }

    /**
     * @return static
     * @throws \Throwable
     */
    public function getActiveConnection(): static
    {
        if ($this->check()) {
            return $this;
        }

        if (!$this->reconnect()) {
            throw new ConnectionException("Connection reconnect failed.");
        }

        return $this;
    }

    /**
     * @return Connection|mixed
     * @throws \Throwable
     */
    public function getDbConnection(): mixed
    {
        try {
            return $this->getActiveConnection()->getConnection();
        } catch (\Throwable $exception) {
            Server::$instance->getLog()->warning("Get connection failed, try again. " . $exception);

            return $this->getActiveConnection()->getConnection();
        }
    }

    /**
     * Constructor arguments for the remote object; signatures differ across classes.
     *
     * @param Config $config
     * @return array
     */
    protected function buildRemoteArgs(Config $config): array
    {
        if (ltrim($config->getRemoteObjectClass(), "\\") === \MongoDB\Client::class) {
            // MongoDB\Client::__construct($uri, $uriOptions, $driverOptions)
            return [$config->getDsn()];
        }

        // Yew\Framework\Mongodb\Connection::__construct($config)
        return [$this->buildConnectionConfig($config)];
    }

    /**
     * @param Config $config
     * @return array
     */
    protected function buildConnectionConfig(Config $config): array
    {
        return [
            "dsn" => $config->getDsn(),
            "defaultDatabaseName" => $config->getDefaultDatabaseName(),
            "options" => $config->getDriverOptions()
        ];
    }
}
