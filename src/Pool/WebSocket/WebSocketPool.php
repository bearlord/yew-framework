<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Pool\WebSocket;

use Swoole\Coroutine\Http\Client;
use Yew\Pool\ConnectionPool;

/**
 * Coroutine pool of WebSocket connections (Swoole Coroutine\Http\Client with
 * upgrade()). The client is reused for many frames after the handshake, so the
 * pool amortises the TCP + WS handshake cost across calls.
 *
 * Usage:
 *   $pool = new WebSocketPool('wss://echo.example.com');
 *   $pool->withConnection(function (Client $c) {
 *       $c->push('hello');
 *       $frame = $c->recv();
 *       return $frame?->data;
 *   });
 */
class WebSocketPool extends ConnectionPool
{
    protected string $host;
    protected int $port;
    protected bool $ssl;
    protected string $path;
    protected array $headers;
    protected array $clientSettings;

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
        $raw = preg_replace('#^(wss?|https?)://#', '', $host);
        $this->host = $raw;
        $this->port = $options['port'] ?? ($this->ssl ? 443 : 80);
        $this->path = $path;
        $this->headers = $options['headers'] ?? [];
        $this->clientSettings = $options['settings'] ?? [];

        parent::__construct($maxConnections, $connectTimeout, $waitTimeout);
    }

    protected function make(): object
    {
        $client = new Client($this->host, $this->port, $this->ssl);

        $settings = $this->clientSettings;
        $settings['connect_timeout'] = $this->connectTimeout;
        if ($this->ssl) {
            $settings['ssl_verify_peer'] = $this->options['sslVerifyPeer'] ?? false;
            $settings['ssl_allow_self_signed'] = $this->options['sslAllowSelfSigned'] ?? false;
            $settings['ssl_cert_file'] = $this->options['sslCertFile'] ?? null;
            $settings['ssl_key_file'] = $this->options['sslKeyFile'] ?? null;
        }
        if (!empty($settings)) {
            $client->set($settings);
        }
        if (!empty($this->headers)) {
            $client->setHeaders($this->headers);
        }

        if (!$client->upgrade($this->path)) {
            $client->close();
            throw new \RuntimeException(
                "WebSocket upgrade {$this->scheme}://{$this->host}:{$this->port}{$this->path} failed"
            );
        }

        return $client;
    }

    protected function isAlive(object $client): bool
    {
        if (!$client instanceof Client || !$client->connected) {
            return false;
        }
        // A non-default websocket status means the handshake did not complete.
        return $client->status === SWOOLE_HTTP_CLIENT_OK
            || $client->status === 101;
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof Client) {
            $client->close();
        }
    }
}
