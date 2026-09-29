<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Mongodb;

use Yew\Framework\Mongodb\Connection;

class Config extends \Yew\Core\Pool\Config
{
    /**
     * mongodb://[username:password@]host1[:port1][,host2[:port2:],...][/dbname]
     *
     * @var string
     */
    protected string $dsn = "";

    /**
     * @var string
     */
    protected string $defaultDatabaseName = "";

    /**
     * Options which are passed to MongoDB\Driver\Manager
     *
     * @var array
     */
    protected array $driverOptions = [];

    /**
     * Whether the connection is hosted by Swoole\RemoteObject\Server
     *
     * @var bool
     */
    protected bool $remoteObjectEnable = true;

    /**
     * @var string
     */
    protected string $remoteObjectHost = "127.0.0.1";

    /**
     * Defaults to Swoole\RemoteObject\Server::DEFAULT_PORT
     *
     * @var int
     */
    protected int $remoteObjectPort = 9567;

    /**
     * @var string
     */
    protected string $remoteObjectApiKey = "";

    /**
     * Class instantiated on the RemoteObject server
     *
     * @var string
     */
    protected string $remoteObjectClass = Connection::class;

    /**
     * @return string
     */
    protected function getKey(): string
    {
        return "mongodb";
    }

    /**
     * @return string
     */
    public function getDsn(): string
    {
        return $this->dsn;
    }

    /**
     * @param string $dsn
     */
    public function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }

    /**
     * @return string
     */
    public function getDefaultDatabaseName(): string
    {
        return $this->defaultDatabaseName;
    }

    /**
     * @param string $defaultDatabaseName
     */
    public function setDefaultDatabaseName(string $defaultDatabaseName): void
    {
        $this->defaultDatabaseName = $defaultDatabaseName;
    }

    /**
     * @return array
     */
    public function getDriverOptions(): array
    {
        return $this->driverOptions;
    }

    /**
     * @param array $driverOptions
     */
    public function setDriverOptions(array $driverOptions): void
    {
        $this->driverOptions = $driverOptions;
    }

    /**
     * @return bool
     */
    public function isRemoteObjectEnable(): bool
    {
        return $this->remoteObjectEnable;
    }

    /**
     * @param bool|int $enable
     */
    public function setRemoteObjectEnable($enable): void
    {
        $this->remoteObjectEnable = (bool)$enable;
    }

    /**
     * @return string
     */
    public function getRemoteObjectHost(): string
    {
        return $this->remoteObjectHost;
    }

    /**
     * @param string $remoteObjectHost
     */
    public function setRemoteObjectHost(string $remoteObjectHost): void
    {
        $this->remoteObjectHost = $remoteObjectHost;
    }

    /**
     * @return int
     */
    public function getRemoteObjectPort(): int
    {
        return $this->remoteObjectPort;
    }

    /**
     * @param int $remoteObjectPort
     */
    public function setRemoteObjectPort(int $remoteObjectPort): void
    {
        $this->remoteObjectPort = $remoteObjectPort;
    }

    /**
     * @return string
     */
    public function getRemoteObjectApiKey(): string
    {
        return $this->remoteObjectApiKey;
    }

    /**
     * @param string $remoteObjectApiKey
     */
    public function setRemoteObjectApiKey(string $remoteObjectApiKey): void
    {
        $this->remoteObjectApiKey = $remoteObjectApiKey;
    }

    /**
     * @return string
     */
    public function getRemoteObjectClass(): string
    {
        return $this->remoteObjectClass;
    }

    /**
     * @param string $remoteObjectClass
     */
    public function setRemoteObjectClass(string $remoteObjectClass): void
    {
        if ($remoteObjectClass !== "") {
            $this->remoteObjectClass = ltrim($remoteObjectClass, "\\");
        }
    }

    /**
     * Build config
     *
     * @return array
     */
    public function buildConfig(): array
    {
        return [
            "name" => $this->getName(),
            "dsn" => $this->getDsn(),
            "defaultDatabaseName" => $this->getDefaultDatabaseName(),
            "driverOptions" => $this->getDriverOptions(),
            "options" => $this->getOptions()
        ];
    }
}
