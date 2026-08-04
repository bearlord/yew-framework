<?php

namespace Yew\Plugins\Actor\Cluster;

use Yew\Core\Server\Port\ServerPort;
use Yew\Core\Server\Server;
use Yew\Core\Server\Port\PortConfig;

/**
 * Framework-managed TCP listener for cross-node actor calls.
 *
 * Declared under `yew.port` in application.yml. The framework binds the socket
 * (Swoole multi-port) and dispatches connections to {@see onTcpReceive} /
 * {@see onTcpClose}, which are forwarded to the PooledTcpRemoteTransport so it
 * no longer needs to start its own server.
 */
class ClusterTcpPort extends ServerPort
{
    public const NAME = 'cluster-tcp';

    /** @var PooledTcpRemoteTransport|null */
    private ?PooledTcpRemoteTransport $transport = null;

    public function __construct(Server $server, PortConfig $portConfig)
    {
        parent::__construct($server, $portConfig);
    }

    public function setTransport(PooledTcpRemoteTransport $transport): void
    {
        $this->transport = $transport;
    }

    public function onTcpReceive(int $fd, int $reactorId, string $data): void
    {
        if ($this->transport !== null) {
            $this->transport->handleReceive($fd, $data);
        }
    }

    public function onTcpClose(int $fd, int $reactorId): void
    {
        if ($this->transport !== null) {
            $this->transport->handleClose($fd);
        }
    }

    public function onTcpConnect(int $fd, int $reactorId): void
    {
    }

    public function onUdpPacket(string $data, array $clientInfo): void
    {
    }

    public function onWsClose(int $fd, int $reactorId): void
    {
    }

    public function onHttpRequest($request, $response): void
    {
    }

    public function onWsMessage($frame): void
    {
    }

    public function onWsOpen($request): void
    {
    }

    public function onWsPassCustomHandshake($request): bool
    {
        return false;
    }
}
