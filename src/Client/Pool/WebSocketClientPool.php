<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client\Pool;

use Yew\Client\WebSocketClient;

/**
 * Coroutine pool of WebSocket connections, wrapping Yew\Client\WebSocketClient.
 *
 * Usage:
 *   $pool = new WebSocketClientPool('wss://echo.example.com', 'wss', '/ws');
 *   $pool->withConnection(function (WebSocketClient $c) {
 *       $c->push('hello');
 *       $frame = $c->recv();
 *       return $frame?->data;
 *   });
 */
class WebSocketClientPool extends ConnectionPool
{
    protected string $host;
    protected int $port;
    protected bool $ssl;
    protected string $path;
    protected array $clientOptions;

    public function __construct(
        string $host,
        protected string $scheme = 'ws',
        string $path = '/',
        protected array $options = [],
        int $maxConnections = 64,
        float $connectTimeout = 3.0,
        float $waitTimeout = 3.0
    ) {
        $this->ssl = (bool) ($options['ssl'] ?? in_array($scheme, ['wss', 'https'], true));
        $this->host = preg_replace('#^(wss?|https?)://#', '', $host);
        $this->port = $options['port'] ?? ($this->ssl ? 443 : 80);
        $this->path = $path;
        $this->clientOptions = array_merge($options, ['connectTimeout' => $connectTimeout]);

        parent::__construct($maxConnections, $connectTimeout, $waitTimeout);
    }

    protected function make(): object
    {
        return new WebSocketClient($this->host, $this->port, $this->path, $this->ssl, $this->clientOptions);
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof WebSocketClient && $client->isConnected();
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof WebSocketClient) {
            $client->close();
        }
    }
}
