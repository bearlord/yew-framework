<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Pool;

use Swoole\Coroutine\Channel;

/**
 * Generic coroutine connection pool, modelled after the pooling pattern used by
 * Cluster's PooledTcpRemoteTransport but kept free of cluster concerns so any
 * Swoole client (TCP, HTTP/HTTPS) can reuse it.
 *
 * Borrowed connections MUST be returned via release(); prefer the scoped
 * withConnection() helper so the connection goes back even on exception.
 */
abstract class ConnectionPool
{
    /**
     * Idle connections waiting to be borrowed.
     */
    protected Channel $pool;

    /**
     * Live connections currently handed out (fd/object => true) so we can spot
     * leaks and skip them when checking health.
     *
     * @var array<int, object>
     */
    protected array $inUse = [];

    protected int $maxConnections;
    protected float $connectTimeout;
    protected float $waitTimeout;

    /**
     * @param int   $maxConnections  Hard cap on live connections.
     * @param float $connectTimeout  Per-connection connect timeout (seconds).
     * @param float $waitTimeout     How long borrow() waits before giving up.
     */
    public function __construct(
        int $maxConnections = 64,
        float $connectTimeout = 3.0,
        float $waitTimeout = 3.0
    ) {
        $this->maxConnections = $maxConnections;
        $this->connectTimeout = $connectTimeout;
        $this->waitTimeout = $waitTimeout;
        $this->pool = new Channel($maxConnections);
    }

    /**
     * Build a fresh, connected client. Implemented per transport.
     *
     * @return object The underlying Swoole client (Coroutine\Client or Http\Client).
     */
    abstract protected function make(): object;

    /**
     * Probe whether a borrowed connection is still usable before reusing it.
     */
    abstract protected function isAlive(object $client): bool;

    /**
     * Tear down a client (called on close / eviction).
     */
    abstract protected function destroy(object $client): void;

    /**
     * Take a connection; creates one if the pool is empty and under the cap,
     * otherwise waits up to waitTimeout. Returns null only on timeout.
     */
    public function borrow(): ?object
    {
        /** @var object|null $client */
        $client = $this->pool->pop($this->waitTimeout);

        if ($client === null) {
            if (count($this->inUse) + $this->pool->length() < $this->maxConnections) {
                $client = $this->make();
            } else {
                return null;
            }
        } elseif (!$this->isAlive($client)) {
            $this->destroy($client);
            $client = $this->make();
        }

        $key = spl_object_id($client);
        $this->inUse[$key] = $client;
        return $client;
    }

    /**
     * Return a connection to the pool. Dead connections are dropped instead.
     */
    public function release(object $client): void
    {
        $key = spl_object_id($client);
        unset($this->inUse[$key]);

        if ($this->isAlive($client) && $this->pool->length() < $this->maxConnections) {
            $this->pool->push($client);
        } else {
            $this->destroy($client);
        }
    }

    /**
     * Run $callback with a borrowed connection and always release it after.
     *
     * @template T
     * @param callable(object):T $callback
     * @return T
     * @throws \RuntimeException When the pool is exhausted.
     */
    public function withConnection(callable $callback)
    {
        $client = $this->borrow();
        if ($client === null) {
            throw new \RuntimeException('Connection pool exhausted, borrow timed out');
        }
        try {
            return $callback($client);
        } finally {
            $this->release($client);
        }
    }

    /**
     * Drop every idle connection.
     */
    public function flush(): void
    {
        while ($conn = $this->pool->pop(0.001)) {
            $this->destroy($conn);
        }
    }

    public function idleCount(): int
    {
        return $this->pool->length();
    }

    public function activeCount(): int
    {
        return count($this->inUse);
    }
}
