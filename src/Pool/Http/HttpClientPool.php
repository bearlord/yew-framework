<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Pool\Http;

use Swoole\Coroutine\Http\Client;
use Yew\Pool\ConnectionPool;

/**
 * Coroutine pool of HTTP/HTTPS connections (Swoole Coroutine\Http\Client).
 *
 * Scheme is taken from $host ('https://...' or the $ssl option); the port
 * defaults to 80 / 443 accordingly when not given.
 *
 * Usage:
 *   $pool = new HttpClientPool('https://api.example.com');
 *   $pool->withConnection(function (Client $c) {
 *       $c->get('/v1/ping');
 *       return $c->body;
 *   });
 */
class HttpClientPool extends ConnectionPool
{
    protected string $host;
    protected int $port;
    protected bool $ssl;
    protected array $headers;
    protected array $clientSettings;

    public function __construct(
        string $host,
        ?int $port = null,
        protected array $options = [],
        int $maxConnections = 64,
        float $connectTimeout = 3.0,
        float $waitTimeout = 3.0
    ) {
        $this->ssl = (bool) ($options['ssl'] ?? str_starts_with($host, 'https://'));
        $raw = preg_replace('#^https?://#', '', $host);
        $this->host = $raw;
        $this->port = $port ?? ($this->ssl ? 443 : 80);
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

        return $client;
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof Client && $client->connected;
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof Client) {
            $client->close();
        }
    }
}
