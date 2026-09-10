<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client\Pool;

use Yew\Client\RedisClient;

/**
 * Coroutine pool of Redis connections, pooling Yew\Client\RedisClient
 * (which wraps Yew\Plugins\Redis\RedisConnection).
 *
 * Borrowed clients MUST be returned via release(); prefer the scoped
 * withConnection() helper so the client goes back even on exception.
 *
 * Usage:
 *   $pool = new RedisClientPool('127.0.0.1', 6379, '', 0);
 *   $val = $pool->withConnection(function (RedisClient $redis) {
 *       $redis->set('foo', 'bar');
 *       return $redis->get('foo');
 *   });
 *
 * @method RedisClient borrow()
 */
class RedisClientPool extends ConnectionPool
{
    /**
     * @param string $host            Redis host
     * @param int    $port            Redis port (default 6379)
     * @param string $auth            Password for AUTH
     * @param int    $database        Selected DB number
     * @param float  $timeout         Connect timeout in seconds
     * @param array  $options         PhpRedis client options
     * @param int    $maxConnections  Hard cap on live connections
     * @param float  $connectTimeout  Per-connection connect timeout (seconds)
     * @param float  $waitTimeout     How long borrow() waits before giving up
     */
    public function __construct(
        protected string $host = '127.0.0.1',
        protected int $port = 6379,
        protected string $auth = '',
        protected int $database = 0,
        protected float $timeout = 0.0,
        protected array $options = [],
        int $maxConnections = 64,
        float $connectTimeout = 3.0,
        float $waitTimeout = 3.0
    ) {
        parent::__construct($maxConnections, $connectTimeout, $waitTimeout);
    }

    protected function make(): object
    {
        return new RedisClient(
            $this->host,
            $this->port,
            $this->auth,
            $this->database,
            $this->timeout,
            $this->options
        );
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof RedisClient && $client->isConnected();
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof RedisClient) {
            $client->close();
        }
    }
}
