<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

/**
 * Lightweight in-process peer record used by the Stage-1 no-gossip fallback
 * ring inside {@see ClusterState}. Once a real gossip engine is mounted via
 * attachGossip(), these records are superseded by GossipClusterState's own
 * ClusterMember table and are no longer consulted.
 */
class PeerState
{
    public const UP = 'up';
    public const SUSPECT = 'suspect';
    public const DOWN = 'down';

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
        string $status = self::UP,
        int $lastHeartbeat = 0
    ) {
        $this->nodeId = $nodeId;
        $this->host = $host;
        $this->port = $port;
        $this->local = $local;
        $this->status = $status;
        $this->lastHeartbeat = $lastHeartbeat > 0 ? $lastHeartbeat : time();
    }
}
