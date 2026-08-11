<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Pool\Tcp;

use Swoole\Coroutine\Client;
use Yew\Pool\ConnectionPool;

/**
 * Coroutine pool of plain TCP (optionally TLS) connections.
 *
 * Usage:
 *   $pool = new TcpPool('127.0.0.1', 9000, ['ssl' => true]);
 *   $pool->withConnection(fn (Client $c) => $c->send($bytes) && $c->recv());
 */
class TcpPool extends ConnectionPool
{
    public function __construct(
        protected string $host,
        protected int $port,
        protected array $options = [],
        int $maxConnections = 64,
        float $connectTimeout = 3.0,
        float $waitTimeout = 3.0
    ) {
        parent::__construct($maxConnections, $connectTimeout, $waitTimeout);
    }

    protected function make(): object
    {
        $ssl = (bool) ($this->options['ssl'] ?? false);
        $sockType = $ssl ? SWOOLE_SOCK_TCP | SWOOLE_SSL : SWOOLE_SOCK_TCP;

        $client = new Client($sockType);
        $client->set([
            'connect_timeout' => $this->connectTimeout,
            'ssl_verify_peer' => $this->options['sslVerifyPeer'] ?? false,
            'ssl_allow_self_signed' => $this->options['sslAllowSelfSigned'] ?? false,
            'ssl_cert_file' => $this->options['sslCertFile'] ?? null,
            'ssl_key_file' => $this->options['sslKeyFile'] ?? null,
        ]);

        if (!$client->connect($this->host, $this->port, $this->connectTimeout)) {
            $code = $client->errCode;
            $client->close();
            throw new \RuntimeException(
                "TcpPool connect {$this->host}:{$this->port} failed (code $code)"
            );
        }

        return $client;
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof Client && $client->isConnected();
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof Client && $client->isConnected()) {
            $client->close();
        }
    }
}
