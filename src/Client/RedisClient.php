<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client;

use Yew\Plugins\Redis\RedisConnection;

/**
 * A single Redis connection client. Thin wrapper over
 * Yew\Plugins\Redis\RedisConnection (PhpRedis based, coroutine friendly) so the
 * same object can be used standalone or pooled by Yew\Client\Pool\RedisClientPool.
 *
 * Connection is opened in the constructor. Every Redis command is available via
 * __call() (e.g. $client->get('k'), $client->lpush('q', $v)) or executeCommand().
 *
 * Usage:
 *   $redis = new RedisClient('127.0.0.1', 6379, '', 0);
 *   $redis->set('foo', 'bar');
 *   $val = $redis->get('foo');
 *
 * @method mixed get($key)
 * @method mixed set($key, $value, ...$options)
 * @method mixed del(...$keys)
 * @method mixed exists(...$keys)
 * @method mixed expire($key, $seconds)
 * @method mixed ttl($key)
 * @method mixed incr($key)
 * @method mixed decr($key)
 * @method mixed hget($key, $field)
 * @method mixed hset($key, $field, $value)
 * @method mixed hgetall($key)
 * @method mixed hdel($key, ...$fields)
 * @method mixed rpush($key, ...$values)
 * @method mixed lpush($key, ...$values)
 * @method mixed lrange($key, $start, $stop)
 * @method mixed publish($channel, $message)
 * @method mixed subscribe(...$channels)
 */
class RedisClient
{
    protected RedisConnection $connection;

    /**
     * @param string $host     Redis host
     * @param int    $port     Redis port (default 6379)
     * @param string $auth     Password for AUTH (empty for no auth)
     * @param int    $database Selected DB number (0 selects the default db)
     * @param float  $timeout  Connect timeout in seconds (0 = unlimited)
     * @param array  $options  PhpRedis client options (serializer, prefix, ...)
     */
    public function __construct(
        protected string $host = '127.0.0.1',
        protected int $port = 6379,
        protected string $auth = '',
        protected int $database = 0,
        protected float $timeout = 0.0,
        protected array $options = []
    ) {
        $this->connection = new RedisConnection([
            'host' => $this->host,
            'port' => $this->port,
            'auth' => $this->auth,
            'database' => $this->database,
            'timeout' => $this->timeout,
            'options' => $this->options,
        ]);

        $this->connection->open();
    }

    /**
     * Execute an arbitrary Redis command by name.
     */
    public function executeCommand(string $name, array $params = []): mixed
    {
        return $this->connection->__call($name, $params);
    }

    /**
     * Pass any Redis command through to the underlying connection.
     */
    public function __call(string $name, array $params = []): mixed
    {
        return $this->connection->__call($name, $params);
    }

    public function ping(): mixed
    {
        return $this->connection->ping();
    }

    public function isConnected(): bool
    {
        try {
            return $this->connection->ping() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function close(): void
    {
        $this->connection->close();
    }

    public function getConnection(): RedisConnection
    {
        return $this->connection;
    }
}
