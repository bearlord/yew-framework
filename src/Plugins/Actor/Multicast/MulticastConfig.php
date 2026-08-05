<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Multicast;

use Yew\Cluster\ClusterBroadcaster;
use Yew\Core\Plugins\Config\BaseConfig;

class MulticastConfig extends BaseConfig
{
    const KEY = "multicast";

    /**
     * @var bool Whether multicast should also fan out to every other cluster node.
     */
    protected bool $clusterEnabled = false;

    /**
     * @var int UDP port used for the cluster-wide multicast transport.
     * Only used when clusterEnabled is true. 0 means "use gossipPort + 1".
     */
    protected int $clusterPort = 0;

    /**
     * @var ClusterBroadcaster|null Injected broadcaster (null when clusterEnabled is false).
     */
    protected ?ClusterBroadcaster $broadcaster = null;

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

    
    /**
     * Construct the multicast config using the "multicast" config key.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return bool
     */
    public function isClusterEnabled(): bool
    {
        return $this->clusterEnabled;
    }

    /**
     * @param bool $clusterEnabled
     */
    public function setClusterEnabled(bool $clusterEnabled): void
    {
        $this->clusterEnabled = $clusterEnabled;
    }

    /**
     * @return int
     */
    public function getClusterPort(): int
    {
        return $this->clusterPort;
    }

    /**
     * @param int $clusterPort
     */
    public function setClusterPort(int $clusterPort): void
    {
        $this->clusterPort = $clusterPort;
    }

    /**
     * @return ClusterBroadcaster|null
     */
    public function getBroadcaster(): ?ClusterBroadcaster
    {
        return $this->broadcaster;
    }

    /**
     * @param ClusterBroadcaster|null $broadcaster
     */
    public function setBroadcaster(?ClusterBroadcaster $broadcaster): void
    {
        $this->broadcaster = $broadcaster;
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