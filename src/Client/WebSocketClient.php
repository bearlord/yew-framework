<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client;

use Swoole\Coroutine\Http\Client;

/**
 * A single WebSocket connection. Thin wrapper over Swoole\Coroutine\Http\Client
 * (after upgrade()) so the same object can be used standalone or pooled by
 * Yew\Client\Pool\WebSocketClient.
 */
class WebSocketClient
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
        protected string $path = '/',
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

        if (!$this->client->upgrade($this->path)) {
            $this->client->close();
            throw new \RuntimeException(
                "WebSocket upgrade {$this->host}:{$this->port}{$this->path} failed"
            );
        }
    }

    /**
     * @param mixed $data    payload to send
     * @param int   $opcode  frame type, default text
     * @param bool  $finish  false to send a fragmented message
     */
    public function push($data, int $opcode = WEBSOCKET_OPCODE_TEXT, bool $finish = true): bool
    {
        return $this->client->push($data, $opcode, $finish);
    }

    /**
     * @param float $timeout seconds to wait; -1 blocks until a frame arrives
     * @return \Swoole\WebSocket\Frame|false
     */
    public function recv(float $timeout = -1)
    {
        return $this->client->recv($timeout);
    }

    public function isConnected(): bool
    {
        return $this->client->connected
            && ($this->client->status === SWOOLE_HTTP_CLIENT_OK || $this->client->status === 101);
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
