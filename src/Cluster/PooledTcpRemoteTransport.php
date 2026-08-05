<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

use Yew\Core\Server\Server;
use Yew\Plugins\Actor\ActorIpcProxy;
use Yew\Plugins\Actor\ActorManager;
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

    /** @var array<int,string> per-connection inbound buffer, keyed by fd */
    private array $recvBuf = [];

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

    /**
     * Inbound data from the framework multi-port TCP listener. Buffers per fd
     * and processes newline-delimited JSON envelopes.
     *
     * @param int $fd The Swoole connection file descriptor
     * @param string $data Raw bytes just received on that connection
     */
    public function handleReceive(int $fd, string $data): void
    {
        $this->recvBuf[$fd] = ($this->recvBuf[$fd] ?? '') . $data;
        while (($pos = strpos($this->recvBuf[$fd], "\n")) !== false) {
            $line = substr($this->recvBuf[$fd], 0, $pos);
            $this->recvBuf[$fd] = substr($this->recvBuf[$fd], $pos + 1);
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            try {
                $envelope = RemoteEnvelope::fromJson($line);
            } catch (\Throwable $e) {
                continue;
            }
            $this->handleInbound($fd, $envelope);
        }
    }

    /**
     * A fd was closed; drop its partial buffer.
     *
     * @param int $fd The Swoole connection file descriptor that closed
     */
    public function handleClose(int $fd): void
    {
        unset($this->recvBuf[$fd]);
    }

    /**
     * Deliver an inbound envelope to the locally-resident actor and, for ask,
     * write the reply back over the same fd (the framework-managed connection).
     *
     * @param int $fd The connection fd to reply on (ask only)
     * @param RemoteEnvelope $env The decoded request envelope
     */
    private function handleInbound(int $fd, RemoteEnvelope $env): void
    {
        $manager = ActorManager::getInstance();
        $info = $manager->getActorInfo($env->actorName);
        if ($info === null) {
            if ($env->kind === RemoteEnvelope::KIND_ASK) {
                $this->sendReply($fd, $this->replyEnvelope($env, null));
            }
            return;
        }

        if ($env->traceId !== null) {
            Tracer::continue($env->traceId);
        }

        $proxy = new ActorIpcProxy($env->actorName, true, 0);
        $result = null;
        if ($env->kind === RemoteEnvelope::KIND_ASK) {
            try {
                $result = $proxy->ask($env->method, $env->arguments, 55);
            } catch (\Throwable $e) {
                $result = ['__error' => $e->getMessage()];
            }
        } else {
            $proxy->tell($env->method, $env->arguments);
        }

        if ($env->kind === RemoteEnvelope::KIND_ASK) {
            $this->sendReply($fd, $this->replyEnvelope($env, $result));
        }
    }

    /**
     * Write a reply envelope back to the connection that asked.
     *
     * @param int $fd The connection fd to send the reply on
     * @param RemoteEnvelope $reply The reply envelope (newline-terminated JSON)
     */
    private function sendReply(int $fd, RemoteEnvelope $reply): void
    {
        $swoole = Server::getInstance()->getServer();
        if ($swoole !== null) {
            $swoole->send($fd, $reply->toJson() . "\n");
        }
    }

    /**
     * Build the reply envelope for a request, carrying the result under
     * arguments['__reply'] and matched to the request by msgId.
     *
     * @param RemoteEnvelope $req The original request envelope
     * @param mixed $result The actor call result (or null if actor is absent)
     * @return RemoteEnvelope The reply envelope
     */
    private function replyEnvelope(RemoteEnvelope $req, $result): RemoteEnvelope
    {
        return new RemoteEnvelope(
            $req->msgId,
            RemoteEnvelope::KIND_ASK, // reuse kind; client distinguishes by msgId
            $req->actorName,
            $req->method,
            ['__reply' => $result],
            $req->traceId,
            $this->localNodeId
        );
    }

    private function newMsgId(): string
    {
        return uniqid('am-', true);
    }
}
