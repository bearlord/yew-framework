<?php
/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>, bearload <565364226@qq.com>
 */

namespace Yew\Plugins\Amqp;

use Yew\Core\Plugins\Config\BaseConfig;

class Config extends \Yew\Core\Pool\Config
{
    const KEY = "amqp";

    /**
     * @var string
     */
    protected string $host = "localhost";

    /**
     * @var int
     */
    protected int $port = 5672;

    /**
     * @var string
     */
    protected string $user;

    /**
     * @var string
     */
    protected string $password;

    /**
     * @var string
     */
    protected string $vhost = "/";

    /**
     * @var bool
     */
    protected bool $insist = false;

    /**
     * @var string
     */
    protected string $loginMethod = "AMQPLAIN";

    /**
     * @var null
     */
    protected $loginResponse = null;

    /**
     * @var string
     */
    protected string $locale = "en_US";

    /**
     * @var float
     */
    protected float $connectionTimeout = 3.0;

    /**
     * @var float
     */
    protected float $readWriteTimeout = 130.0;

    /**
     * @var null
     */
    protected $context = null;

    /**
     * @var bool
     */
    protected bool $keepAlive = true;

    /**
     * @var int
     */
    protected int $heartBeat = 60;

    /**
     * @return string
     */
    protected function getKey(): string
    {
        return 'amqp';
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
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
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
     * @return string
     */
    public function getVhost(): string
    {
        return $this->vhost;
    }

    /**
     * @param string $vhost
     */
    public function setVhost(string $vhost): void
    {
        $this->vhost = $vhost;
    }

    /**
     * @return bool
     */
    public function isInsist(): bool
    {
        return $this->insist;
    }

    /**
     * @param bool $insist
     */
    public function setInsist(bool $insist): void
    {
        $this->insist = $insist;
    }

    /**
     * @return string
     */
    public function getLoginMethod(): string
    {
        return $this->loginMethod;
    }

    /**
     * @param string $loginMethod
     */
    public function setLoginMethod(string $loginMethod): void
    {
        $this->loginMethod = $loginMethod;
    }

    /**
     * @return null
     */
    public function getLoginResponse()
    {
        return $this->loginResponse;
    }

    /**
     * @param null $loginResponse
     */
    public function setLoginResponse($loginResponse): void
    {
        $this->loginResponse = $loginResponse;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @return float
     */
    public function getConnectionTimeout(): float
    {
        return $this->connectionTimeout;
    }

    /**
     * @param float $connectionTimeout
     */
    public function setConnectionTimeout(float $connectionTimeout): void
    {
        $this->connectionTimeout = $connectionTimeout;
    }

    /**
     * @return float
     */
    public function getReadWriteTimeout(): float
    {
        return $this->readWriteTimeout;
    }

    /**
     * @param float $readWriteTimeout
     */
    public function setReadWriteTimeout(float $readWriteTimeout): void
    {
        $this->readWriteTimeout = $readWriteTimeout;
    }

    /**
     * @return null
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param null $context
     */
    public function setContext($context): void
    {
        $this->context = $context;
    }

    /**
     * @return bool
     */
    public function isKeepAlive(): bool
    {
        return $this->keepAlive;
    }

    /**
     * @param bool $keepAlive
     */
    public function setKeepAlive(bool $keepAlive): void
    {
        $this->keepAlive = $keepAlive;
    }

    /**
     * @return int
     */
    public function getHeartBeat(): int
    {
        return $this->heartBeat;
    }

    /**
     * @param int $heartBeat
     */
    public function setHeartBeat(int $heartBeat): void
    {
        $this->heartBeat = $heartBeat;
    }

    /**
     * @param array $values
     * @return void
     */
    public function setValues(array $values): void
    {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Build config
     * @throws AmqpException
     */
    public function buildConfig()
    {
        if (!extension_loaded("bcmath")) {
            throw new AmqpException("Amqp requires the Bcmath PHP extension");
        }

        if(empty($this->host)){
            throw new AmqpException("Amqp host must be set");
        }
    }
}