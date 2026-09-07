<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Snowflake\MetaGenerator;

use Yew\Snowflake\MetaGenerator;

/**
 * Default meta generator with a fixed dataCenterId/workerId resolved from
 * configuration, environment, or the current process id. Unlike the legacy
 * RandomMilliSecondMetaGenerator, the worker id is stable for the process
 * lifetime, which is required for id uniqueness.
 */
class SysMetaGenerator extends MetaGenerator
{
    protected int $dataCenterId;

    protected int $workerId;

    /**
     * @param int      $beginTimestamp epoch seconds; multiplied by 1000 internally
     * @param int|null $dataCenterId   fixed id, defaults to env SNOWFLAKE_DATA_CENTER_ID or pid
     * @param int|null $workerId       fixed id, defaults to env SNOWFLAKE_WORKER_ID or pid
     */
    public function __construct(int $beginTimestamp = 0, ?int $dataCenterId = null, ?int $workerId = null)
    {
        parent::__construct($beginTimestamp * 1000);

        $maxDataCenterId = $this->getConfiguration()->maxDataCenterId();
        $maxWorkerId = $this->getConfiguration()->maxWorkerId();

        $this->dataCenterId = $this->resolveId($dataCenterId, 'SNOWFLAKE_DATA_CENTER_ID', $maxDataCenterId);
        $this->workerId = $this->resolveId($workerId, 'SNOWFLAKE_WORKER_ID', $maxWorkerId);
    }

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
