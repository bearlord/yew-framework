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
    /** @var int Gossip incarnation counter; bumped on restart to reject stale heartbeats (zombie revival guard). */
    public int $incarnation;

    public function __construct(
        string $nodeId,
        string $host,
        int $port,
        bool $local,
        string $status,
        int $lastHeartbeat,
        int $weight = 1,
        int $incarnation = 0
    ) {
        $this->nodeId = $nodeId;
        $this->host = $host;
        $this->port = $port;
        $this->local = $local;
        $this->status = $status;
        $this->lastHeartbeat = $lastHeartbeat;
        $this->weight = $weight;
        $this->incarnation = $incarnation;
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

    /**
     * Serialize to a plain row (array) for GossipMessage transport.
     */
    public function toRow(): array
    {
        return [
            'nodeId'         => $this->nodeId,
            'host'           => $this->host,
            'port'           => $this->port,
            'local'          => $this->local,
            'status'         => $this->status,
            'lastHeartbeat'  => $this->lastHeartbeat,
            'weight'         => $this->weight,
            'incarnation'    => $this->incarnation,
        ];
    }

    /**
     * Reconstruct a ClusterMember from a row produced by toRow().
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['nodeId'] ?? ''),
            (string) ($row['host'] ?? ''),
            (int) ($row['port'] ?? 0),
            (bool) ($row['local'] ?? false),
            (string) ($row['status'] ?? self::STATUS_DOWN),
            (int) ($row['lastHeartbeat'] ?? 0),
            (int) ($row['weight'] ?? 1),
            (int) ($row['incarnation'] ?? 0)
        );
    }
}
