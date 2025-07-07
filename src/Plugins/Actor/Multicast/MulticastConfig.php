<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Multicast;

use Yew\Core\Plugins\Config\BaseConfig;

class MulticastConfig extends BaseConfig
{
    const KEY = "multicast";

    /**
     * @var int
     */
    protected int $cacheChannelCount = 10000;

    /**
     * @var int
     */
    protected int $channelMaxLength = 256;

    /**
     * @var int
     */
    protected int $cacheActorCount = 10000;

    /**
     * @var int
     */
    protected int $actorMaxLength = 256;
    
    /**
     * @var string
     */
    protected string $processName = "multicast";

    
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return int
     */
    public function getCacheChannelCount(): int
    {
        return $this->cacheChannelCount;
    }

    /**
     * @param int $cacheChannelCount
     */
    public function setCacheChannelCount(int $cacheChannelCount): void
    {
        $this->cacheChannelCount = $cacheChannelCount;
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
    public function getChannelMaxLength(): int
    {
        return $this->channelMaxLength;
    }

    /**
     * @param int $channelMaxLength
     */
    public function setChannelMaxLength(int $channelMaxLength): void
    {
        $this->channelMaxLength = $channelMaxLength;
    }

    /**
     * @return int
     */
    public function getCacheActorCount(): int
    {
        return $this->cacheActorCount;
    }

    /**
     * @param int $cacheActorCount
     */
    public function setCacheActorCount(int $cacheActorCount): void
    {
        $this->cacheActorCount = $cacheActorCount;
    }

    /**
     * @return int
     */
    public function getActorMaxLength(): int
    {
        return $this->actorMaxLength;
    }

    /**
     * @param int $actorMaxLength
     */
    public function setActorMaxLength(int $actorMaxLength): void
    {
        $this->actorMaxLength = $actorMaxLength;
    }

}