<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic;

use Yew\Core\Plugins\Config\BaseConfig;

class TopicConfig extends BaseConfig
{
    const KEY = "topic";

    /**
     * @var int
     */
    protected int $cacheTopicCount = 10000;

    /**
     * @var int
     */
    protected int $topicMaxLength = 256;

    /**
     * In the helper process by default, other names can be set, and a new process will be created
     * @var string
     */
    protected string $processName = "helper";


    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return int
     */
    public function getCacheTopicCount(): int
    {
        return $this->cacheTopicCount;
    }

    /**
     * @param int $cacheTopicCount
     */
    public function setCacheTopicCount(int $cacheTopicCount): void
    {
        $this->cacheTopicCount = $cacheTopicCount;
    }

    /**
     * @return string
     */
    public function getProcessName(): string
    {
        return $this->processName;
    }

    /**
     * @param string $processName
     */
    public function setProcessName(string $processName): void
    {
        $this->processName = $processName;
    }

    /**
     * @return int
     */
    public function getTopicMaxLength(): int
    {
        return $this->topicMaxLength;
    }

    /**
     * @param int $topicMaxLength
     */
    public function setTopicMaxLength(int $topicMaxLength): void
    {
        $this->topicMaxLength = $topicMaxLength;
    }
}