<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

use Yew\Plugins\Actor\Telemetry\Tracer;

/**
 * Connection-pooled variant of {@see TcpRemoteTransport}.
 *
 * Reuses established TCP connections per remote node instead of opening a new
 * one per call, which matters for high-frequency ask traffic across the
 * cluster. Connections are borrowed from a per-node pool and returned when the
 * reply (or timeout) arrives. Broken connections are dropped and recreated.
 *
 * tell still uses a throwaway connection from the pool (fire-and-forget, no
 * reply read); ask borrows a connection, reads the reply envelope, then returns
 * it to the pool.
 */
class PooledTcpRemoteTransport implements RemoteTransport
{
    private string $host;
    private int $port;
    private string $localNodeId;
    private int $poolSize;
    private float $idleTimeout;

    /** @var array<string,\Swoole\Coroutine\Channel> host:port => pooled clients */
    private array $pools = [];

    public function __construct(
        string $host,
        int $port,
        string $localNodeId,
        int $poolSize = 16,
        float $idleTimeout = 60.0
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->localNodeId = $localNodeId;
        $this->poolSize = $poolSize;
        $this->idleTimeout = $idleTimeout;
    }

    public function start(): void
    {
        // Pooling is lazy; nothing to bind. Kept for interface symmetry.
    }

    private function poolFor(string $key): \Swoole\Coroutine\Channel
    {
        if (!isset($this->pools[$key])) {
            $this->pools[$key] = new \Swoole\Coroutine\Channel($this->poolSize);
        }
        return $this->pools[$key];
    }

    private function borrow(string $host, int $port): ?\Swoole\Coroutine\Client
    {
        $key = $host . ':' . $port;
        $pool = $this->poolFor($key);
        $client = null;
        if ($pool->isEmpty()) {
            $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
            if (!$client->connect($host, $port, 5.0)) {
                return null;
            }
        } else {
            /** @var \Swoole\Coroutine\Client $client */
            $client = $pool->pop(0.1);
            if ($client === false || !$client->isConnected()) {
                $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
                if (!$client->connect($host, $port, 5.0)) {
                    return null;
                }
            }
        }
        return $client;
    }

    private function release(string $host, int $port, ?\Swoole\Coroutine\Client $client): void
    {
        if ($client === null) {
            return;
        }
        $key = $host . ':' . $port;
        $pool = $this->poolFor($key);
        if ($pool->isFull()) {
            $client->close();
            return;
        }
        $pool->push($client);
    }

    public function tell(Location $location, string $method, array $arguments, ?string $traceId): bool
    {
        $env = new RemoteEnvelope(
            $this->newMsgId(), RemoteEnvelope::KIND_TELL,
            $location->getActorName() ?? '', $method, $arguments, $traceId, $this->localNodeId
        );
        $node = $location->getNode();
        $client = $this->borrow($node->getHost(), $node->getPort());
        if ($client === null) {
            return false;
        }
        $ok = (bool) $client->send($env->toJson() . "\n");
        $this->release($node->getHost(), $node->getPort(), $client);
        return $ok;
    }

    public function ask(Location $location, string $method, array $arguments, ?string $traceId, float $timeOut)
    {
        $env = new RemoteEnvelope(
            $this->newMsgId(), RemoteEnvelope::KIND_ASK,
            $location->getActorName() ?? '', $method, $arguments, $traceId, $this->localNodeId
        );
        $node = $location->getNode();
        $client = $this->borrow($node->getHost(), $node->getPort());
        if ($client === null) {
            return null;
        }
        try {
            if (!$client->send($env->toJson() . "\n")) {
                return null;
            }
            $line = $client->recv(max(1.0, $timeOut + 5));
            if (!is_string($line) || trim($line) === '') {
                return null;
            }
            $reply = RemoteEnvelope::fromJson(trim($line));
            return $reply->msgId === $env->msgId ? ($reply->arguments['__reply'] ?? null) : null;
        } finally {
            $this->release($node->getHost(), $node->getPort(), $client);
        }
    }

    public function supports(Location $location): bool
    {
        $node = $location->getNode();
        return $node !== null && !$node->isLocal();
    }

    private function newMsgId(): string
    {
        return uniqid('am-', true);
    }
}
