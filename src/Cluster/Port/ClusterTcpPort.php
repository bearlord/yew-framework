<?php

namespace Yew\Cluster\Port;

use Yew\Cluster\Transport\Transfer;
use Yew\Core\Server\Port\ServerPort;
use Yew\Core\Server\Server;
use Yew\Core\Server\Config\PortConfig;

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

    /**
     * The transport serving inbound envelopes. Typed as Transfer so only
     * transports that actually support fd-based framing (e.g.
     * PooledTcpRemoteTransport) are accepted; LocalTransport does not qualify.
     */
    private ?Transfer $transport = null;

    public function __construct(Server $server, PortConfig $portConfig)
    {
        parent::__construct($server, $portConfig);
    }

    /**
     * @param Transfer $transport Transport implementing fd-based framing.
     */
    public function setTransport(Transfer $transport): void
    {
        $this->transport = $transport;
    }

    public function onTcpReceive(int $fd, int $reactorId, string $data): void
    {
        if ($this->transport === null) {
            Server::$instance->getLog()->error(sprintf(
                "cluster-tcp: onTcpReceive fd=%d but transport is NULL; dropping %d bytes",
                $fd, strlen($data)
            ));
        }

        $this->transport?->handleReceive($fd, $data);
    }

    public function onTcpClose(int $fd, int $reactorId): void
    {
        $this->transport?->handleClose($fd);
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
