<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client\Pool;

use Yew\Client\HttpClient;

/**
 * Coroutine pool of HTTP/HTTPS connections, wrapping Yew\Client\HttpClient.
 *
 * Scheme is taken from $host ('https://...' or the $ssl option); the port
 * defaults to 80 / 443 accordingly when not given.
 *
 * Usage:
 *   $pool = new HttpPool('https://api.example.com');
 *   $pool->withConnection(function (HttpClient $c) {
 *       $c->get('/v1/ping');
 *       return $c->getBody();
 *   });
 */
class HttpPool extends ConnectionPool
{
    protected string $host;
    protected int $port;
    protected bool $ssl;
    protected array $clientOptions;

    public function __construct(
        string $host,
        ?int $port = null,
        protected array $options = [],
        int $maxConnections = 64,
        float $connectTimeout = 3.0,
        float $waitTimeout = 3.0
    ) {
        $this->ssl = (bool) ($options['ssl'] ?? str_starts_with($host, 'https://'));
        $this->host = preg_replace('#^https?://#', '', $host);
        $this->port = $port ?? ($this->ssl ? 443 : 80);
        $this->clientOptions = array_merge($options, ['connectTimeout' => $connectTimeout]);

        parent::__construct($maxConnections, $connectTimeout, $waitTimeout);
    }

    protected function make(): object
    {
        return new HttpClient($this->host, $this->port, $this->ssl, $this->clientOptions);
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof HttpClient && $client->isConnected();
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof HttpClient) {
            $client->close();
        }
    }
}
