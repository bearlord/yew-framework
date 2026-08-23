<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client;

use Swoole\Coroutine\Http\Client;

/**
 * A single HTTP/HTTPS connection. Thin wrapper over Swoole\Coroutine\Http\Client
 * so the same object can be used standalone or pooled by Yew\Client\Pool\HttpClient.
 */
class HttpClient
{
    protected Client $client;

    /**
     * @param array $options Supported keys: settings (array passed to client->set),
     *                        headers (default request headers), sslVerifyPeer,
     *                        sslAllowSelfSigned, sslCertFile, sslKeyFile
     */
    public function __construct(
        protected string $host,
        protected int $port,
        protected bool $ssl = false,
        protected array $options = []
    ) {
        $this->client = new Client($this->host, $this->port, $this->ssl);

        $settings = $this->options['settings'] ?? [];
        if ($this->ssl) {
            $settings['ssl_verify_peer'] = $this->options['sslVerifyPeer'] ?? false;
            $settings['ssl_allow_self_signed'] = $this->options['sslAllowSelfSigned'] ?? false;
            $settings['ssl_cert_file'] = $this->options['sslCertFile'] ?? null;
            $settings['ssl_key_file'] = $this->options['sslKeyFile'] ?? null;
        }
        if (!empty($settings)) {
            $this->client->set($settings);
        }
        if (!empty($this->options['headers'])) {
            $this->client->setHeaders($this->options['headers']);
        }
    }

    /**
     * @param string $path    request path, should start with "/"
     * @param array  $headers extra request headers
     */
    public function get(string $path, array $headers = []): bool
    {
        return $this->client->get($path, $headers);
    }

    /**
     * @param string $path    request path, should start with "/"
     * @param mixed  $data    request body, string or array
     * @param array  $headers extra request headers
     */
    public function post(string $path, $data, array $headers = []): bool
    {
        return $this->client->post($path, $data, $headers);
    }

    /**
     * @param string $method HTTP method, e.g. "GET", "POST"
     */
    public function execute(string $path, string $method = 'GET'): bool
    {
        return $this->client->execute($path, $method);
    }

    public function getStatusCode(): int
    {
        return $this->client->statusCode;
    }

    public function getBody(): string
    {
        return $this->client->body;
    }

    public function getHeaders(): array
    {
        return $this->client->headers;
    }

    public function isConnected(): bool
    {
        return $this->client->connected;
    }

    public function close(): void
    {
        $this->client->close();
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
