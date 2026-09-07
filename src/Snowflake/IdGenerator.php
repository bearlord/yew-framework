<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Snowflake;


class IdGenerator implements IdGeneratorInterface
{
    /**
     * @var MetaGeneratorInterface
     */
    protected MetaGeneratorInterface $metaGenerator;

    /**
     * @var ConfigurationInterface
     */
    protected ConfigurationInterface $config;

    /**
     * IdGenerator constructor
     */
    public function __construct(MetaGeneratorInterface $metaGenerator)
    {
        $this->metaGenerator = $metaGenerator;
        $this->config = $metaGenerator->getConfiguration();
    }

    /**
     * @return MetaGeneratorInterface
     */
    public function getMetaGenerator(): MetaGeneratorInterface
    {
        return $this->metaGenerator;
    }

    /**
     * @param MetaGeneratorInterface $metaGenerator
     */
    public function setMetaGenerator(MetaGeneratorInterface $metaGenerator): void
    {
        $this->metaGenerator = $metaGenerator;
    }


    /**
     * @param Meta|null $meta
     * @return int
     */
    public function generate(?Meta $meta = null): int
    {
        $meta = $this->getMeta($meta);

        // Guard against out-of-range fields leaking into sibling bit fields.
        $maxDataCenterId = $this->config->maxDataCenterId();
        $maxWorkerId = $this->config->maxWorkerId();
        $maxSequence = $this->config->maxSequence();

        if ($meta->getDataCenterId() < 0 || $meta->getDataCenterId() > $maxDataCenterId) {
            throw new \InvalidArgumentException(sprintf('dataCenterId %d out of range [0, %d].', $meta->getDataCenterId(), $maxDataCenterId));
        }
        if ($meta->getWorkerId() < 0 || $meta->getWorkerId() > $maxWorkerId) {
            throw new \InvalidArgumentException(sprintf('workerId %d out of range [0, %d].', $meta->getWorkerId(), $maxWorkerId));
        }
        if ($meta->getSequence() < 0 || $meta->getSequence() > $maxSequence) {
            throw new \InvalidArgumentException(sprintf('sequence %d out of range [0, %d].', $meta->getSequence(), $maxSequence));
        }

        $interval = $meta->getTimeInterval() << $this->config->getTimestampLeftShift();
        $dataCenterId = $meta->getDataCenterId() << $this->config->getDataCenterIdShift();
        $workerId = $meta->getWorkerId() << $this->config->getWorkerIdShift();

        return $interval | $dataCenterId | $workerId | $meta->getSequence();
    }

    /**
     * @param int $id
     * @return Meta
     */
    public function degenerate(int $id): Meta
    {
        $tsShift = $this->config->getTimestampLeftShift();
        $dcShift = $this->config->getDataCenterIdShift();
        $workerShift = $this->config->getWorkerIdShift();

        $interval = $id >> $tsShift;
        $dataCenterId = ($id >> $dcShift) & ((1 << $this->config->getDataCenterIdBits()) - 1);
        $workerId = ($id >> $workerShift) & ((1 << $this->config->getWorkerIdBits()) - 1);
        $sequence = $id & ((1 << $this->config->getSequenceBits()) - 1);

        $beginTimestamp = $this->metaGenerator->getBeginTimestamp();

        return new Meta(
            $dataCenterId,
            $workerId,
            $sequence,
            $interval + $beginTimestamp,
            $beginTimestamp
        );
    }

    /**
     * @param Meta|null $meta
     * @return Meta
     */
    protected function getMeta(?Meta $meta = null): Meta
    {
        if (is_null($meta)) {
            return $this->metaGenerator->generate();
        }

        return $meta;
    }
}