<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Transport;

/**
 * Real UDP gossip transport. Binds a UDP socket for inbound digests and
 * broadcasts / unicasts outbound digests to the cluster subnet.
 *
 * Two operating modes:
 *  - self-managed (default): binds its own Swoole coroutine UDP socket.
 *  - framework-managed: when {@see setManaged}(true) is called, the socket is
 *    NOT bound. Inbound datagrams are pushed via {@see handlePacket()} (fed by
 *    the framework's multi-port UDP listener) and outbound traffic is sent via
 *    the {@see setSender()} callback (the master Swoole server's sendto).
 */
class UdpGossipTransport implements GossipTransport
{
    private string $bindHost;
    private int $bindPort;
    private string $broadcastTarget; // "host:port" or multicast group
    private ?\Swoole\Coroutine\Socket $socket = null;
    private \Swoole\Channel $inbox;

    private bool $managed = false;
    /** @var callable|null (string $host, int $port, string $payload): void */
    private $sender = null;

    public function __construct(string $bindHost, int $bindPort, string $broadcastTarget)
    {
        $this->bindHost = $bindHost;
        $this->bindPort = $bindPort;
        $this->broadcastTarget = $broadcastTarget;
        $this->inbox = new \Swoole\Channel(1024);
    }

    /**
     * Switch to framework-managed mode (no self-bound socket).
     */
    public function setManaged(bool $managed): void
    {
        $this->managed = $managed;
    }

    /**
     * Set the outbound sender used in framework-managed mode.
     * Signature: (string $host, int $port, string $payload): void
     */
    public function setSender(callable $sender): void
    {
        $this->sender = $sender;
    }

    /**
     * Feed an inbound datagram (called by the framework multi-port UDP listener).
     */
    public function handlePacket(string $data, array $clientInfo): void
    {
        $this->inbox->push($data);
    }

    /**
     * Start the receiver. In framework-managed mode this is a no-op because the
     * framework's multi-port UDP listener owns the socket.
     */
    public function start(): void
    {
        if ($this->managed) {
            return;
        }
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

    /**
     * Broadcast a gossip payload to the configured broadcast/multicast target.
     *
     * @param string $payload Serialized gossip message (JSON digest envelope)
     */
    public function broadcast(string $payload): void
    {
        if ($this->sender !== null) {
            [$host, $port] = explode(':', $this->broadcastTarget);
            ($this->sender)($host, (int) $port, $payload);
            return;
        }
        if ($this->socket === null) {
            return;
        }
        [$host, $port] = explode(':', $this->broadcastTarget);
        $this->socket->sendto($host, (int) $port, $payload);
    }

    /**
     * Send a gossip payload to a single peer.
     *
     * @param string $peer Target as "host:port"
     * @param string $payload Serialized gossip message (JSON digest envelope)
     */
    public function sendTo(string $peer, string $payload): void
    {
        if ($this->sender !== null) {
            [$host, $port] = explode(':', $peer);
            ($this->sender)($host, (int) $port, $payload);
            return;
        }
        if ($this->socket === null) {
            return;
        }
        [$host, $port] = explode(':', $peer);
        $this->socket->sendto($host, (int) $port, $payload);
    }

    /**
     * Pop the next inbound datagram from the inbox, blocking up to $timeout seconds.
     *
     * @param float $timeout Max seconds to wait for a datagram
     * @return string|null The datagram, or null on timeout/empty
     */
    public function receive(float $timeout): ?string
    {
        $pop = $this->inbox->pop($timeout);
        return is_string($pop) ? $pop : null;
    }
}
