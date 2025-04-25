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
        $interval = $id >> $this->config->getTimestampLeftShift();
        $dataCenterId = $id >> $this->config->getDataCenterIdShift();
        $workerId = $id >> $this->config->getWorkerIdShift();

        return new Meta(
            $interval << $this->config->getDataCenterIdBits() ^ $dataCenterId,
            $dataCenterId << $this->config->getWorkerIdBits() ^ $workerId,
            $workerId << $this->config->getSequenceBits() ^ $id,
            $interval + $this->metaGenerator->getBeginTimestamp(),
            $this->metaGenerator->getBeginTimestamp()
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