<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Snowflake;

use Yew\Snowflake\Exception\SnowflakeException;

abstract class MetaGenerator implements MetaGeneratorInterface
{
    /**
     * @var ConfigurationInterface
     */
    protected $configuration;

    protected int $sequence = 0;

    protected int $lastTimestamp = 0;

    protected int $beginTimestamp = 0;

    abstract public function getDataCenterId(): int;

    abstract public function getWorkerId(): int;

    abstract public function getTimestamp(): int;

    abstract public function getNextTimestamp(): int;

    /**
     * @param int $beginTimestamp
     */
    public function __construct(int $beginTimestamp)
    {
        $this->configuration = $this->getOrSetConfiguration();
        $this->lastTimestamp = $this->getTimestamp();
        $this->beginTimestamp = $beginTimestamp;
    }

    /**
     * @return ConfigurationInterface
     */
    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    /**
     * @param ConfigurationInterface $configuration
     */
    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @return Configuration|ConfigurationInterface
     */
    protected function getOrSetConfiguration()
    {
        if ($this->configuration === null) {
            $this->configuration = new Configuration();
        }

        return $this->configuration;
    }

    /**
     * @return int
     */
    public function getBeginTimestamp(): int
    {
        return $this->beginTimestamp;
    }

    /**
     * @return Meta
     */
    public function generate(): Meta
    {
        $timestamp = $this->getTimestamp();

        if ($timestamp == $this->lastTimestamp) {
            // Within the same millisecond: advance the sequence. If it wraps
            // around, the millisecond is exhausted, so spin until the next one.
            $this->sequence = $this->sequence + 1;
            if ($this->sequence > $this->configuration->maxSequence()) {
                $timestamp = $this->getNextTimestamp();
                $this->sequence = 0;
            }
        } elseif ($timestamp < $this->lastTimestamp) {
            // Clock moved backwards: refuse to generate to avoid collisions.
            $this->clockMovedBackwards($timestamp, $this->lastTimestamp);
            $this->sequence = 0;
            $timestamp = $this->lastTimestamp;
        } else {
            $this->sequence = 0;
        }

        if ($timestamp < $this->beginTimestamp) {
            throw new SnowflakeException(sprintf('The beginTimestamp %d is invalid, because it smaller than timestamp %d.', $this->beginTimestamp, $timestamp));
        }

        $this->lastTimestamp = $timestamp;

        return new Meta($this->getDataCenterId(), $this->getWorkerId(), $this->sequence, $timestamp, $this->beginTimestamp);
    }

    /**
     * @param int $timestamp
     * @param int $lastTimestamp
     */
    protected function clockMovedBackwards(int $timestamp, int $lastTimestamp)
    {
        throw new SnowflakeException(sprintf('Clock moved backwards. Refusing to generate id for %d milliseconds.', $lastTimestamp - $timestamp));
    }
}
