<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client\Pool;

use Yew\Client\TcpClient;

/**
 * Coroutine pool of TCP connections (optionally TLS), wrapping Yew\Client\TcpClient.
 *
 * Usage:
 *   $pool = new TcpPool('127.0.0.1', 9000, ['ssl' => true]);
 *   $pool->withConnection(fn (TcpClient $c) => $c->send($bytes) && $c->recv());
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
        return new TcpClient($this->host, $this->port, array_merge(
            $this->options,
            ['connectTimeout' => $this->connectTimeout]
        ));
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof TcpClient && $client->isConnected();
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof TcpClient) {
            $client->close();
        }
    }
}
