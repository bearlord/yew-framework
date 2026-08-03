<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

/**
 * Real UDP gossip transport. Binds a UDP socket for inbound digests and
 * broadcasts / unicasts outbound digests to the cluster subnet.
 *
 * Uses a Swoole coroutine UDP server so it coexists with the rest of the
 * event loop. Broadcasts go to the configured gossip address (e.g.
 * 239.0.0.1 multicast or a directed-broadcast subnet); pushes/pulls target a
 * single peer address.
 */
class UdpGossipTransport implements GossipTransport
{
    private string $bindHost;
    private int $bindPort;
    private string $broadcastTarget; // "host:port" or multicast group
    private ?\Swoole\Coroutine\Socket $socket = null;
    private \Swoole\Channel $inbox;

    public function __construct(string $bindHost, int $bindPort, string $broadcastTarget)
    {
        $this->bindHost = $bindHost;
        $this->bindPort = $bindPort;
        $this->broadcastTarget = $broadcastTarget;
        $this->inbox = new \Swoole\Channel(1024);
    }

    /**
     * Start the receiver coroutine. Call once from the event loop.
     */
    public function start(): void
    {
        $this->socket = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
        if (!$this->socket->bind($this->bindHost, $this->bindPort)) {
            return;
        }
        goWithContext(function () {
            while ($this->socket !== null) {
                $peer = null;
                $data = $this->socket->recvfrom(65535, 0, $peer);
                if ($data === false || $data === '') {
                    continue;
                }
                $this->inbox->push($data);
            }
        });
    }

    public function broadcast(string $payload): void
    {
        if ($this->socket === null) {
            return;
        }
        [$host, $port] = explode(':', $this->broadcastTarget);
        $this->socket->sendto($host, (int) $port, $payload);
    }

    public function sendTo(string $peer, string $payload): void
    {
        if ($this->socket === null) {
            return;
        }
        [$host, $port] = explode(':', $peer);
        $this->socket->sendto($host, (int) $port, $payload);
    }

    public function receive(float $timeout): ?string
    {
        $pop = $this->inbox->pop($timeout);
        return is_string($pop) ? $pop : null;
    }
}
