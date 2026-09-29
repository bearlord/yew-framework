<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Mongodb;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Framework\Mongodb\Connection;

/**
 * Client connection and runtime parameters for Swoole\RemoteObject\Server.
 *
 * Configuration example (application.yml):
 * ```yaml
 * yew:
 *   mongodbRemoteObject:
 *     enable: true
 *     host: '127.0.0.1'
 *     port: 9567
 *     workerNum: 16
 *     serverMode: 2          # 2 = SWOOLE_PROCESS (default), 1 = SWOOLE_BASE
 *     apiKey: ''
 *     remoteClass: 'Yew\Framework\Mongodb\Connection'   # or 'MongoDB\Client'
 * ```
 */
class RemoteObjectConfig extends BaseConfig
{
    /**
     * @var bool
     */
    protected bool $enable = true;

    /**
     * @var string
     */
    protected string $host = "127.0.0.1";

    /**
     * Swoole\RemoteObject\Server::DEFAULT_PORT
     *
     * @var int
     */
    protected int $port = 9567;

    /**
     * Number of server worker processes
     *
     * @var int
     */
    protected int $workerNum = 16;

    /**
     * Process mode: 2 = SWOOLE_PROCESS (default), 1 = SWOOLE_BASE
     *
     * @var int
     */
    protected int $serverMode = 2;

    /**
     * Class instantiated on the remote server.
     * - Yew\Framework\Mongodb\Connection: framework's built-in MongoDB wrapper (default)
     * - MongoDB\Client: requires composer require mongodb/mongodb
     *
     * @var string
     */
    protected string $remoteClass = Connection::class;

    /**
     * @var string
     */
    protected string $apiKey = "";

    /**
     * Classes allowed to be instantiated on the server; falls back to the default whitelist when empty
     *
     * @var array
     */
    protected array $allowedClasses = [];

    public function __construct()
    {
        parent::__construct("mongodbRemoteObject");
    }

    /**
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enable;
    }

    /**
     * @param bool|int $enable
     */
    public function setEnable($enable): void
    {
        $this->enable = (bool)$enable;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * @return int
     */
    public function getWorkerNum(): int
    {
        return $this->workerNum;
    }

    /**
     * @param int $workerNum
     */
    public function setWorkerNum(int $workerNum): void
    {
        $this->workerNum = $workerNum;
    }

    /**
     * @return int
     */
    public function getServerMode(): int
    {
        return $this->serverMode;
    }

    /**
     * @param int $serverMode
     */
    public function setServerMode(int $serverMode): void
    {
        $this->serverMode = $serverMode;
    }

    /**
     * @return string
     */
    public function getRemoteClass(): string
    {
        return $this->remoteClass;
    }

    /**
     * @param string $remoteClass
     */
    public function setRemoteClass(string $remoteClass): void
    {
        if ($remoteClass !== "") {
            $this->remoteClass = ltrim($remoteClass, "\\");
        }
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return array
     */
    public function getAllowedClasses(): array
    {
        return $this->allowedClasses;
    }

    /**
     * @param array $allowedClasses
     */
    public function setAllowedClasses(array $allowedClasses): void
    {
        $this->allowedClasses = $allowedClasses;
    }

    /**
     * Default whitelist: MongoDB driver + high-level client + Yew's Connection
     *
     * @return array
     */
    public function getDefaultAllowedClasses(): array
    {
        return [
            "MongoDB\\Driver\\Manager",
            "MongoDB\\Driver\\Query",
            "MongoDB\\Driver\\Command",
            "MongoDB\\Driver\\BulkWrite",
            "MongoDB\\Driver\\WriteConcern",
            "MongoDB\\Driver\\ReadPreference",
            "MongoDB\\Driver\\Cursor",
            "MongoDB\\Client",
            Connection::class
        ];
    }

    /**
     * @return array
     */
    public function buildConfig(): array
    {
        return [
            "enable" => $this->isEnable(),
            "host" => $this->getHost(),
            "port" => $this->getPort(),
            "workerNum" => $this->getWorkerNum(),
            "serverMode" => $this->getServerMode(),
            "remoteClass" => $this->getRemoteClass(),
            "allowedClasses" => $this->getAllowedClasses()
        ];
    }
}
