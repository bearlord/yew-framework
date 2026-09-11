<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Queue\Drivers\Redis;

use Yew\Plugins\Redis\GetRedis;
use Yew\Framework\Base\InvalidArgumentException;
use Yew\Framework\Di\Instance;
use Yew\Framework\Queue\Cli\Queue as CliQueue;
use Yew\Framework\Redis\Connection;
use Yew\Yew;

/**
 * Redis Queue.
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
class Queue extends CliQueue
{
    use GetRedis;

    /**
     * @var Connection|array|string
     */
    public $redis = 'redis';

    /**
     * @var string
     */
    public $channel = 'queue';

    /**
     * Number of priority levels. Each level maps to its own waiting list,
     * so level 0 is served before level 1, and so on.
     * @var int
     */
    public $priorityLevels = 3;

    /**
     * Level assigned to jobs pushed without an explicit priority.
     * Defaults to the lowest level so any prioritized job jumps ahead.
     * @var int
     */
    public $defaultPriority = 2;

    /**
     * Max jobs handled concurrently by a single worker.
     * 1 = synchronous (handle one job at a time, default and backward-compatible);
     * >1 = asynchronous (run up to this many jobs in parallel coroutines).
     * @var int
     */
    public $concurrency = 1;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->redis = Yew::createObject(Connection::class);
    }

    /**
     * Listens redis-queue and runs new jobs.
     * It can be used as daemon process.
     *
     * @param int $timeout number of seconds to wait a job.
     * @throws InvalidArgumentException when params are invalid.
     * @return null|int exit code.
     */
    public function listen($timeout = 3)
    {
        if (!is_numeric($timeout)) {
            throw new InvalidArgumentException('Timeout must be numeric.');
        }
        if ($timeout < 1) {
            throw new InvalidArgumentException('Timeout must be greater than zero.');
        }
        
        return $this->run(true, $timeout);
    }


    /**
     * Listens queue and runs each job.
     *
     * @param bool $repeat whether to continue listening when queue is empty.
     * @param int $timeout number of seconds to wait for next message.
     * @return null|int exit code.
     * @internal for worker command only.
     * @since 2.0.2
     */
    public function run($repeat, $timeout = 0)
    {
        return $this->runWorker(function (callable $canContinue) use ($repeat, $timeout) {
            $concurrency = max(1, (int) $this->concurrency);

            // Synchronous mode (default): handle one job at a time, in place.
            if ($concurrency === 1) {
                while ($canContinue()) {
                    $payload = $this->reserve($timeout);
                    if ($payload !== null) {
                        list($id, $message, $ttr, $attempt) = $payload;
                        if ($this->handleMessage($id, $message, $ttr, $attempt)) {
                            $this->delete($id);
                        }
                    } elseif (!$repeat) {
                        break;
                    }
                    // reserve() already blocks (BRPOP) when $timeout > 0, so a tight
                    // 1ms poll loop is only needed in non-blocking mode ($timeout == 0).
                    // A 1ms busy-poll pins a full CPU core; back off to a sane idle
                    // interval instead.
                    if ($timeout <= 0) {
                        \Swoole\Coroutine::sleep(0.05);
                    }
                }
                return;
            }

            // Asynchronous mode: run up to $concurrency jobs concurrently.
            // All Redis access (reserve/moveExpired/delete) stays on the main
            // coroutine; only the job execution is offloaded to child coroutines,
            // and the result is sent back through a channel. This keeps the single
            // Redis connection free of concurrent use (which would corrupt commands).
            $slots = new \Swoole\Coroutine\Channel($concurrency);
            for ($i = 0; $i < $concurrency; $i++) {
                $slots->push(1);
            }
            $done = new \Swoole\Coroutine\Channel($concurrency * 2);

            while ($canContinue()) {
                $slots->pop();
                $payload = $this->reserve($timeout);
                if ($payload === null) {
                    $slots->push(1);
                    if (!$repeat) {
                        break;
                    }
                    if ($timeout <= 0) {
                        \Swoole\Coroutine::sleep(0.05);
                    }
                    continue;
                }

                list($id, $message, $ttr, $attempt) = $payload;
                \Swoole\Coroutine::create(function () use ($id, $message, $ttr, $attempt, $done, $slots) {
                    try {
                        $ok = $this->handleMessage($id, $message, $ttr, $attempt);
                    } catch (\Throwable $e) {
                        $ok = false;
                    } finally {
                        $done->push([$id, $ok]);
                        $slots->push(1);
                    }
                });

                // Reap finished jobs (non-blocking) so they get deleted promptly.
                while ($finished = $done->pop(0.001)) {
                    list($fid, $fok) = $finished;
                    if ($fok) {
                        $this->delete($fid);
                    }
                }
            }

            // Drain remaining results before leaving.
            while ($finished = $done->pop(1)) {
                list($fid, $fok) = $finished;
                if ($fok) {
                    $this->delete($fid);
                }
            }
        });
    }

    /**
     * @inheritdoc
     */
    public function status($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new InvalidArgumentException("Unknown message ID: $id.");
        }

        if ($this->redis->hexists("$this->channel.attempts", $id)) {
            return self::STATUS_RESERVED;
        }

        if ($this->redis->hexists("$this->channel.messages", $id)) {
            return self::STATUS_WAITING;
        }

        return self::STATUS_DONE;
    }

    /**
     * Clears the queue.
     *
     * @since 2.0.1
     */
    public function clear()
    {
        while (!$this->redis->set("$this->channel.moving_lock", true, 'NX')) {
            \Swoole\Coroutine::sleep(0.01);
        }
        $this->redis->executeCommand('DEL', $this->redis->keys("$this->channel.*"));
    }

    /**
     * Removes a job by ID.
     *
     * @param int $id of a job
     * @return bool
     * @since 2.0.1
     */
    public function remove($id)
    {
        while (!$this->redis->set("$this->channel.moving_lock", true, ['NX', 'EX' => 1])) {
            \Swoole\Coroutine::sleep(0.01);
        }
        if ($this->redis->hdel("$this->channel.messages", $id)) {
            $this->redis->zrem("$this->channel.delayed", $id);
            $this->redis->zrem("$this->channel.reserved", $id);
            foreach ($this->waitingKeys() as $key) {
                $this->redis->lrem($key, 0, $id);
            }
            $this->redis->hdel("$this->channel.priorities", $id);
            $this->redis->hdel("$this->channel.attempts", $id);

            return true;
        }

        return false;
    }

    /**
     * @param int $timeout timeout
     * @return array|null payload
     */
    public function reserve($timeout)
    {
        // Moves delayed and reserved jobs into waiting lists with lock for one second
        if ($this->redis->set("$this->channel.moving_lock", true, ['NX', 'EX' => 1])) {
            $this->moveExpired("$this->channel.delayed");
            $this->moveExpired("$this->channel.reserved");
        }

        // Find a new waiting message, highest priority list first.
        $id = null;
        $waitingKeys = $this->waitingKeys();
        if (!$timeout) {
            foreach ($waitingKeys as $key) {
                $id = $this->redis->rpop($key);
                if ($id !== null && $id !== false) {
                    break;
                }
            }
        } elseif ($result = $this->redis->executeCommand('BRPOP', array_merge($waitingKeys, [$timeout]))) {
            $id = $result[1];
        }
        if (!$id) {
            return null;
        }

        $payload = $this->redis->hget("$this->channel.messages", $id);
        if ($payload === false) {
            // The message may have been removed/clear-ed between rpop and hget.
            return null;
        }
        list($ttr, $message) = explode(';', $payload, 2);
        $this->redis->zadd("$this->channel.reserved", time() + $ttr, $id);
        $attempt = $this->redis->hincrby("$this->channel.attempts", $id, 1);

        return [$id, $message, $ttr, $attempt];
    }

    /**
     * @param string $from
     */
    protected function moveExpired($from)
    {
        $now = time();
        if ($expired = $this->redis->zrevrangebyscore($from, $now, '-inf')) {
            $this->redis->zremrangebyscore($from, '-inf', $now);
            foreach ($expired as $id) {
                $level = (int) $this->redis->hget("$this->channel.priorities", $id);
                $this->redis->rpush($this->waitingKey($level), $id);
            }
        }
    }

    /**
     * Deletes message by ID.
     *
     * @param int $id of a message
     */
    protected function delete($id)
    {
        $this->redis->zrem("$this->channel.reserved", $id);
        $this->redis->hdel("$this->channel.attempts", $id);
        $this->redis->hdel("$this->channel.messages", $id);
        $this->redis->hdel("$this->channel.priorities", $id);
    }

    /**
     * @inheritdoc
     */
    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        $level = $this->priorityToLevel($priority);

        $id = $this->redis->incr("$this->channel.message_id");
        $this->redis->hset("$this->channel.messages", $id, "$ttr;$message");
        if (!$delay) {
            $this->redis->lpush($this->waitingKey($level), $id);
        } else {
            $this->redis->zadd("$this->channel.delayed", time() + $delay, $id);
            // remember the level so a delayed job lands on the right waiting
            // list once it expires
            $this->redis->hset("$this->channel.priorities", $id, $level);
        }

        return $id;
    }

    /**
     * Map a job priority to a waiting-list level. Smaller priority => smaller
     * level => served first. Out-of-range values are clamped.
     *
     * @param mixed $priority
     * @return int
     */
    protected function priorityToLevel($priority): int
    {
        if ($priority === null) {
            return $this->defaultPriority;
        }
        $level = (int) $priority;
        if ($level < 0) {
            $level = 0;
        } elseif ($level > $this->priorityLevels - 1) {
            $level = $this->priorityLevels - 1;
        }
        return $level;
    }

    /**
     * @param int $level
     * @return string
     */
    protected function waitingKey(int $level): string
    {
        return "$this->channel.waiting.$level";
    }

    /**
     * Waiting-list keys ordered from highest to lowest priority.
     * @return string[]
     */
    protected function waitingKeys(): array
    {
        $keys = [];
        for ($i = 0; $i < $this->priorityLevels; $i++) {
            $keys[] = $this->waitingKey($i);
        }
        return $keys;
    }
}
