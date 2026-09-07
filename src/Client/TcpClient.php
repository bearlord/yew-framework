<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client;

use Swoole\Coroutine\Client;

/**
 * A single TCP connection (optionally TLS). Thin wrapper over
 * Swoole\Coroutine\Client that encapsulates connect/ssl settings so the same
 * object can be used standalone or pooled by Yew\Client\Pool\TcpClient.
 */
class TcpClient
{
    protected Client $client;

    /**
     * @param array $options Supported keys: connectTimeout (float seconds),
     *                        ssl, sslVerifyPeer, sslAllowSelfSigned, sslCertFile,
     *                        sslKeyFile
     */
    public function __construct(
        protected string $host,
        protected int $port,
        protected array $options = []
    ) {
        $ssl = (bool) ($this->options['ssl'] ?? false);
        $sockType = $ssl ? SWOOLE_SOCK_TCP | SWOOLE_SSL : SWOOLE_SOCK_TCP;

        $connectTimeout = $this->options['connectTimeout'] ?? 3.0;
        $this->client = new Client($sockType);
        $this->client->set([
            'connect_timeout' => $connectTimeout,
            'ssl_verify_peer' => $this->options['sslVerifyPeer'] ?? false,
            'ssl_allow_self_signed' => $this->options['sslAllowSelfSigned'] ?? false,
            'ssl_cert_file' => $this->options['sslCertFile'] ?? null,
            'ssl_key_file' => $this->options['sslKeyFile'] ?? null,
        ]);

        if (!$this->client->connect($this->host, $this->port, $connectTimeout)) {
            $code = $this->client->errCode;
            $this->client->close();
            throw new \RuntimeException(
                "Tcp connect {$this->host}:{$this->port} failed (code $code)"
            );
        }
    }

    public function send(string $data): bool
    {
        return $this->client->send($data) !== false;
    }

    /**
     * @param float $timeout seconds to wait; -1 blocks until data arrives
     * @return string|false
     */
    public function recv(float $timeout = -1)
    {
        return $this->client->recv($timeout);
    }

    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }

    public function close(): void
    {
        if ($this->client->isConnected()) {
            $this->client->close();
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
