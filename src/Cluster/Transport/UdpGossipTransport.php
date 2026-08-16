<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Transport;

use Yew\Core\Plugins\Logger\GetLogger;

/**
 * Real UDP gossip transport: binds a UDP socket for inbound digests and
 * unicasts/broadcasts outbound digests.
 *
 * Modes:
 *  - self-managed (default): binds its own Swoole coroutine UDP socket.
 *  - framework-managed: setManaged(true) skips binding; inbound datagrams come
 *    via handlePacket() (framework multi-port UDP listener) and outbound via the
 *    setSender() callback (master Swoole server's sendto).
 */
class UdpGossipTransport implements GossipTransport
{
    use GetLogger;

    private string $bindHost;
    private int $bindPort;
    private string $broadcastTarget; // "host:port" or multicast group
    private ?\Swoole\Coroutine\Socket $socket = null;
    // Lazily created in start() (worker process) so constructing this in the
    // master process does not activate the coroutine runtime / event loop.
    private ?\Swoole\Coroutine\Channel $inbox = null;

    private bool $managed = false;
    /** @var callable|null (string $host, int $port, string $payload): void */
    private $sender = null;

    public function __construct(string $bindHost, int $bindPort, string $broadcastTarget)
    {
        $this->bindHost = $bindHost;
        $this->bindPort = $bindPort;
        $this->broadcastTarget = $broadcastTarget;
    }

    /**
     * Defensive logger: never fatal when the logger is not yet wired.
     */
    private function log(string $message): void
    {
        try {
            $logger = $this->getLogger();
            if ($logger !== null) {
                $logger->debug($message);
                return;
            }
        } catch (\Throwable $e) {
            // fall through to error_log
        }
        error_log($message);
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
     * Handle an inbound datagram from the framework-managed multi-port UDP listener.
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
        // Lazily create the inbox channel now (worker process, event loop up).
        if ($this->inbox === null) {
            $this->inbox = new \Swoole\Coroutine\Channel(1024);
        }
        if ($this->managed) {
            return;
        }
        $this->socket = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
        if (!$this->socket->bind($this->bindHost, $this->bindPort)) {
            $this->log("[gossip-udp] BIND FAILED host={$this->bindHost} port={$this->bindPort} err=" . ($this->socket->errMsg ?? '?'));
            return;
        }
        $this->log("[gossip-udp] BOUND host={$this->bindHost} port={$this->bindPort} ok");
        // Respawn the recv loop so a transient exception (e.g. a socket error
        // during a network flap) cannot permanently kill inbound gossip.
        goWithContext(function () {
            while ($this->socket !== null) {
                try {
                    $ticks = 0;
                    while ($this->socket !== null) {
                        $peer = [];
                        $data = $this->socket->recvfrom($peer, 1.0);
                        if ($data === false || $data === '') {
                            $ticks++;
                            if ($ticks % 30 === 0) {
                                $this->log("[gossip-udp] recvfrom idle ticks={$ticks}");
                            }
                            continue;
                        }
                        $ticks = 0;
                        $this->log("[gossip-udp] RECV " . strlen($data) . " bytes from " . ($peer['address'] ?? '?') . ':' . ($peer['port'] ?? '?'));
                        $this->inbox->push($data);
                    }
                } catch (\Throwable $e) {
                    $this->log("[gossip-udp] recv loop died: " . $e->getMessage() . " — respawning in 1s");
                    \Swoole\Coroutine::sleep(1);
                }
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
