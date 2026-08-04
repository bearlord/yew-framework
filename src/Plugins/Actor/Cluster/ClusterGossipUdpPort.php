<?php

namespace Yew\Plugins\Actor\Cluster;

use Yew\Core\Server\Port\ServerPort;
use Yew\Core\Server\Server;
use Yew\Core\Server\Port\PortConfig;

/**
 * Framework-managed UDP listener for gossip membership traffic.
 *
 * Declared under `yew.port` in application.yml. The framework binds the socket
 * (Swoole multi-port) and dispatches datagrams to {@see onUdpPacket}, which
 * feeds the GossipClusterState's wire transport instead of letting the
 * transport open its own socket.
 */
class ClusterGossipUdpPort extends ServerPort
{
    public const NAME = 'cluster-gossip';

    /** @var GossipClusterState|null */
    private ?GossipClusterState $state = null;

    public function __construct(Server $server, PortConfig $portConfig)
    {
        parent::__construct($server, $portConfig);
    }

    /**
     * Attach the running cluster state so its UDP transport runs in
     * framework-managed mode (no self-bind) and replies are sent back via the
     * master Swoole server's sendto().
     */
    public function setClusterState(GossipClusterState $state): void
    {
        $this->state = $state;
        $transport = $state->getTransport();
        if ($transport instanceof UdpGossipTransport) {
            $transport->setManaged(true);
            $transport->setSender(function (string $host, int $port, string $payload) {
                $swoole = Server::$instance->getServer();
                if ($swoole !== null) {
                    $swoole->sendto($host, $port, $payload);
                }
            });
        }
    }

    public function onUdpPacket(string $data, array $clientInfo): void
    {
        if ($this->state === null) {
            return;
        }
        $transport = $this->state->getTransport();
        if ($transport instanceof UdpGossipTransport) {
            $transport->handlePacket($data, $clientInfo);
        }
    }

    public function onTcpConnect(int $fd, int $reactorId): void
    {
    }

    public function onTcpReceive(int $fd, int $reactorId, string $data): void
    {
    }

    public function onTcpClose(int $fd, int $reactorId): void
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
