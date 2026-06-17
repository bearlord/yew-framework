<?php
/**
 * Yew framework
 * @author Lu Fei <lufei@simps.io>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Mqtt\Client;

use Yew\Mqtt\Client\Config\ClientConfig;
use Yew\Mqtt\Protocol\ProtocolV3;
use Yew\Mqtt\Protocol\ProtocolV5;

class Client extends BaseClient
{
    /**
     * Record subscribed topics for automatic re-subscription after reconnection
     *
     * @var array
     */
    protected array $subscribedTopics = [];

    /**
     * @param string $host
     * @param int $port
     * @param ClientConfig $config
     * @param int $clientType
     */
    public function __construct(
        string $host,
        int $port,
        ClientConfig $config,
        int $clientType = self::COROUTINE_CLIENT_TYPE
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->config = $config;
        $this->clientType = $clientType;

        if ($this->isCoroutineClientType()) {
            $this->client = new \Swoole\Coroutine\Client($config->getSockType());
        } else {
            $this->client = new \Swoole\Client($config->getSockType());
        }

        $this->client->set($config->getSwooleConfig());
        if (!$this->client->connect($host, $port)) {
            $this->reConnect();
        }
    }

    /**
     * @return void
     */
    protected function reConnect(): void
    {
        $result = false;
        $maxAttempts = $this->getConfig()->getMaxAttempts();
        $delay = $this->getConfig()->getDelay();
        while (!$result) {
            if ($maxAttempts === 0) {
                $this->handleException();
            }
            $this->sleep($delay);
            $this->client->close();
            $result = $this->client->connect($this->getHost(), $this->getPort());
            if ($maxAttempts > 0) {
                $maxAttempts--;
            }
        }
    }

    /**
     * @param array $data
     * @param bool $response
     * @return bool
     * @throws \Throwable
     */
    public function send(array $data, bool $response = true)
    {
        if ($this->getConfig()->isMQTT5()) {
            $package = ProtocolV5::pack($data);
        } else {
            $package = ProtocolV3::pack($data);
        }

        $this->client->send($package);

        if ($response) {
            return $this->recv();
        }

        return true;
    }

    /**
     * @return array|bool
     * @throws \Throwable
     */
    public function recv()
    {
        $response = $this->getResponse();
        if ($response === "" || !$this->client->isConnected()) {
            $this->reConnect();
            $this->connect($this->getConnectData("clean_session") ?? true, $this->getConnectData("will") ?? []);

            // Re-subscribe to previously subscribed topics after reconnection
            if (!empty($this->subscribedTopics)) {
                parent::subscribe($this->subscribedTopics);
            }
        } elseif ($response === false && $this->client->errCode !== SOCKET_ETIMEDOUT) {
            $this->handleException();
        } elseif (is_string($response) && strlen($response) > 0) {
            if ($this->getConfig()->isMQTT5()) {
                return ProtocolV5::unpack($response);
            }

            return ProtocolV3::unpack($response);
        }

        return true;
    }

    /**
     * @param array $topics
     * @return bool
     */
    public function subscribe(array $topics)
    {
        $this->subscribedTopics = $topics;
        return parent::subscribe($topics);
    }

    /**
     * @return bool|string
     */
    protected function getResponse()
    {
        if ($this->isCoroutineClientType()) {
            $response = $this->client->recv();
        } else {
            $write = $error = [];
            $read = [$this->client];
            $n = swoole_client_select($read, $write, $error);
            if ($n > 0) {
                $response = $this->client->recv();
            } else {
                $response = true;
            }
        }

        return $response;
    }
}
