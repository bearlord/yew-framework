<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Redis;

use Yew\Plugins\Redis\Exception\RedisException;

class Config extends \Yew\Core\Pool\Config
{
    /**
     * @var string
     */
    protected string $name = "";

    /**
     * @var string
     */
    protected string $host = "localhost";

    /**
     * @var int
     */
    protected int $port = 6379;

    /**
     * @var mixed
     */
    protected $auth = null;

    /**
     * @var string
     */
    protected string $password = "";

    /**
     * @var int
     */
    protected int $database = 0;

    /**
     * @var float
     */
    protected float $timeout = 0.0;

    /**
     * @var mixed
     */
    protected $reserved = null;

    /**
     * @var int
     */
    protected int $retryInterval = 0;

    /**
     * @var float
     */
    protected float $readTimeout = 0.0;

    /**
     * @var array
     */
    protected array $cluster = [];

    /**
     * @var array
     */
    protected array $sentinel = [];


    /**
     * @return string
     */
    protected function getKey(): string
    {
        return 'redis';
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
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @return array
     * @throws RedisException
     */
    public function buildConfig(): array
    {
        if (!extension_loaded('redis')) {
            throw new RedisException('Redis extension is not loaded');
        }
        if (empty($this->name)) {
            throw new RedisException('Redis name must be set');
        }
        if (empty($this->host)) {
            throw new RedisException('Redis host must be set');
        }

        return [
            "name" => $this->name,
            "host" => $this->host,
            "port" => $this->port,
            "auth" => $this->auth,
            "database" => $this->database,
            "timeout" => $this->timeout,
            "reserved" => $this->reserved,
            "retryInterval" => $this->retryInterval,
            "readTimeout" => $this->readTimeout,
            "cluster" => $this->cluster,
            "sentinel" => $this->sentinel,
            "options" => $this->options
        ];
    }

    /**
     * @return int|null
     */
    public function getPort(): ?int
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
    public function getDatabase(): int
    {
        return $this->database;
    }

    /**
     * @param int $database
     */
    public function setDatabase(int $database): void
    {
        $this->database = $database;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed|null
     */
    public function getAuth()
    {
        return $this->auth;
    }

    /**
     * @param mixed|null $auth
     */
    public function setAuth($auth): void
    {
        $this->auth = $auth;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function setTimeout(float $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return mixed|null
     */
    public function getReserved()
    {
        return $this->reserved;
    }

    /**
     * @param mixed|null $reserved
     */
    public function setReserved($reserved): void
    {
        $this->reserved = $reserved;
    }

    /**
     * @return int
     */
    public function getRetryInterval(): int
    {
        return $this->retryInterval;
    }

    /**
     * @param int $retryInterval
     * @return void
     */
    public function setRetryInterval(int $retryInterval): void
    {
        $this->retryInterval = $retryInterval;
    }

    /**
     * @return float
     */
    public function getReadTimeout(): float
    {
        return $this->readTimeout;
    }

    /**
     * @param float $readTimeout
     * @return void
     */
    public function setReadTimeout(float $readTimeout): void
    {
        $this->readTimeout = $readTimeout;
    }

    /**
     * @return array
     */
    public function getCluster(): array
    {
        return $this->cluster;
    }

    /**
     * @param array $cluster
     * @return void
     */
    public function setCluster(array $cluster): void
    {
        $this->cluster = $cluster;
    }

    /**
     * @return array
     */
    public function getSentinel(): array
    {
        return $this->sentinel;
    }

    /**
     * @param array $sentinel
     * @return void
     */
    public function setSentinel(array $sentinel): void
    {
        $this->sentinel = $sentinel;
    }
}
