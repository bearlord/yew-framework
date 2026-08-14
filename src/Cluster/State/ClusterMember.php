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

    public function __construct(
        string $nodeId,
        string $host,
        int $port,
        bool $local,
        string $status,
        int $lastHeartbeat
    ) {
        $this->nodeId = $nodeId;
        $this->host = $host;
        $this->port = $port;
        $this->local = $local;
        $this->status = $status;
        $this->lastHeartbeat = $lastHeartbeat;
    }

    public function isLocal(): bool
    {
        return $this->local;
    }
}
