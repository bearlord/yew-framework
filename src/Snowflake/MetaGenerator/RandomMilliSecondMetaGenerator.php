<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Snowflake\MetaGenerator;

use Yew\Snowflake\MetaGenerator;

class RandomMilliSecondMetaGenerator extends MetaGenerator
{
    protected int $dataCenterId;

    protected int $workerId;

    /**
     * @param int      $beginTimestamp epoch seconds; multiplied by 1000 internally
     * @param int|null $dataCenterId   fixed id, defaults to a value derived from env/pid
     * @param int|null $workerId       fixed id, defaults to a value derived from env/pid
     */
    public function __construct(int $beginTimestamp = 0, ?int $dataCenterId = null, ?int $workerId = null)
    {
        parent::__construct($beginTimestamp * 1000);

        $maxDataCenterId = $this->getConfiguration()->maxDataCenterId();
        $maxWorkerId = $this->getConfiguration()->maxWorkerId();

        $this->dataCenterId = $this->resolveId($dataCenterId, 'SNOWFLAKE_DATA_CENTER_ID', $maxDataCenterId);
        $this->workerId = $this->resolveId($workerId, 'SNOWFLAKE_WORKER_ID', $maxWorkerId);
    }

    /**
     * Pick a fixed id: use the explicit value if given, else an env var,
     * else fall back to the current pid masked into range.
     */
    protected function resolveId(?int $value, string $envKey, int $max): int
    {
        if ($value !== null) {
            return $value & $max;
        }
        $env = (int) (getenv($envKey) ?: 0);
        if ($env > 0) {
            return $env & $max;
        }
        return (getmypid() ?: 0) & $max;
    }

    public function getDataCenterId(): int
    {
        return $this->dataCenterId;
    }

    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    public function getTimestamp(): int
    {
        return intval(microtime(true) * 1000);
    }

    public function getNextTimestamp(): int
    {
        $timestamp = $this->getTimestamp();
        while ($timestamp <= $this->lastTimestamp) {
            \Swoole\Coroutine::sleep(0.001);
            $timestamp = $this->getTimestamp();
        }

        return $timestamp;
    }
}
