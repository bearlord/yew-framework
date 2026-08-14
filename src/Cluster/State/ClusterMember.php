<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

/**
 * In-memory cluster member record owned exclusively by {@see ClusterState}.
 *
 * Unlike the legacy gossip implementation (which mirrored this across a shared
 * Swoole\Table and every actor worker), there is exactly one authoritative copy
 * per node, living in the cluster-state process.
 */
class ClusterMember
{
    public const STATUS_UP = 'up';
    public const STATUS_SUSPECT = 'suspect';
    public const STATUS_DOWN = 'down';

    public string $nodeId;
    public string $host;
    public int $port;
    public bool $local;
    public string $status;
    public int $lastHeartbeat;
    /** @var int Virtual-replica weight for the consistent-hash ring (1 = even). */
    public int $weight;

    public function __construct(
        string $nodeId,
        string $host,
        int $port,
        bool $local,
        string $status,
        int $lastHeartbeat,
        int $weight = 1
    ) {
        $this->nodeId = $nodeId;
        $this->host = $host;
        $this->port = $port;
        $this->local = $local;
        $this->status = $status;
        $this->lastHeartbeat = $lastHeartbeat;
        $this->weight = $weight;
    }

    public function isLocal(): bool
    {
        return $this->local;
    }

    /**
     * A member is considered alive (eligible for routing / gossip fan-out) when
     * it is UP or SUSPECT. A node in the DOWN state is excluded until it proves
     * liveness again (strictly higher incarnation on a later observe()).
     */
    public function isAlive(): bool
    {
        return $this->status === self::STATUS_UP || $this->status === self::STATUS_SUSPECT;
    }
}
