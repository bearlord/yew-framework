<?php

namespace Yew\Plugins\RateLimit\Storage;

use Malkusch\Lock\Mutex\Mutex;
use Malkusch\Lock\Mutex\RedisMutex;
use Yew\Framework\Base\BaseObject;
use Yew\Framework\Exception\InvalidArgumentException;
use Yew\Plugins\Redis\GetRedis;
use Yew\TokenBucket\Storage\Storage;
use Yew\TokenBucket\Storage\StorageException;
use Yew\TokenBucket\Util\DoublePacker;

class RedisStorage extends BaseObject implements StorageInterface, Storage
{
    use GetRedis;

    public const KEY_PREFIX = 'rateLimiter:storage:';

    private Mutex $mutex;

    /**
     * @var string the key
     */
    private $key;

    /**
     * @var \Yew\Plugins\Redis\RedisConnection
     */
    private $redis;

    private array $options;

    public function __construct(string $key = "", $timeout = 0, array $options = [])
    {
        $this->redis = $this->redis()->getDriver();

        $this->options = $options;
        $this->key = self::KEY_PREFIX . $key;

        $this->mutex = new RedisMutex($this->redis, $this->key, $timeout);
    }

    public function bootstrap($microtime): void
    {
        $this->setMicrotime($microtime);
    }

    public function isBootstrapped(): bool
    {
        try {
            return (bool)$this->redis->exists($this->key);
        } catch (InvalidArgumentException $e) {
            throw new StorageException('Failed to check for key existence', 0, $e);
        }
    }

    public function remove(): void
    {
        try {
            if (!$this->redis->del($this->key)) {
                throw new StorageException('Failed to delete key');
            }
        } catch (InvalidArgumentException $e) {
            throw new StorageException('Failed to delete key', 0, $e);
        }
    }

    /**
     * @SuppressWarnings(PHPMD)
     * @param float $microtime
     * @throws StorageException
     */
    public function setMicrotime($microtime): void
    {
        try {
            $data = DoublePacker::pack($microtime);

            if (!$this->redis->set($this->key, $data)) {
                throw new StorageException('Failed to store microtime');
            }
            if (!empty($this->options['expired_time']) && $this->options['expired_time'] > 0) {
                $this->redis->expire($this->key, $this->options['expired_time']);
            }
        } catch (InvalidArgumentException $e) {
            throw new StorageException('Failed to store microtime', 0, $e);
        }
    }

    /**
     * @SuppressWarnings(PHPMD)
     * @throws StorageException
     */
    public function getMicrotime(): float
    {
        try {
            $data = $this->redis->get($this->key);
            if ($data === false) {
                throw new StorageException('Failed to get microtime');
            }
            return DoublePacker::unpack($data);
        } catch (InvalidArgumentException $e) {
            throw new StorageException('Failed to get microtime', 0, $e);
        }
    }

    public function getMutex(): Mutex
    {
        return $this->mutex;
    }

    public function letMicrotimeUnchanged(): void
    {
    }
}