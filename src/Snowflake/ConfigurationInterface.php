<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Snowflake;

interface ConfigurationInterface
{
    /**
     * Get the maximum worker id bits.
     */
    public function maxWorkerId(): int;

    /**
     * Get the maximum data center id bits.
     */
    public function maxDataCenterId(): int;

    /**
     * Get the maximum sequence bits.
     */
    public function maxSequence(): int;

    /**
     * Get the timestamp left shift.
     */
    public function getTimestampLeftShift(): int;

    /**
     * Get the data center id shift.
     */
    public function getDataCenterIdShift(): int;

    /**
     * Get the worker id shift.
     */
    public function getWorkerIdShift(): int;

    /**
     * Get the timestamp bits.
     */
    public function getTimestampBits(): int;

    /**
     * Get the data center id bits.
     */
    public function getDataCenterIdBits(): int;

    /**
     * Get the worker id bits.
     */
    public function getWorkerIdBits(): int;

    /**
     * Get the sequence bits.
     */
    public function getSequenceBits(): int;
}
