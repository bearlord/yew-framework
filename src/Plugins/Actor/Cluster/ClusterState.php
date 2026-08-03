<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugins\Logger\GetLogger;

/**
 * Shared-memory cluster membership service.
 *
 * This is the pragmatic stand-in for a gossip protocol in a single Swoole
 * server / multi-process deployment. Every actor worker process can read the
 * same Swoole Table, so "who is alive" is visible cluster-wide without any
 * network traffic. A periodic {@see tick()}:
 *   - refreshes the local node's heartbeat,
 *   - marks nodes stale past the suspicion window as SUSPECT,
 *   - marks nodes past the failure window as DOWN,
 *   - fires the onMembershipChange callback so the shard router can rebalance.
 *
 * Swapping this for a UDP gossip layer later only means replacing the Table
 * read/write with broadcast + merge; the public surface (aliveNodes, tick,
 * registerListener) stays identical.
 */
class ClusterState implements ClusterStateInterface
{
    use GetLogger;

    private const DEFAULT_HEARTBEAT_INTERVAL = 1;   // seconds
    private const DEFAULT_SUSPECT_AFTER = 3;        // missed heartbeats
    private const DEFAULT_DOWN_AFTER = 8;           // missed heartbeats

    private string $localNodeId;
    private Table $memberTable;
    private float $suspectAfter;
    private float $downAfter;
    private array $listeners = [];

    /**
     * @param string $localNodeId Stable id of this node
     * @param int $maxNodes Capacity of the membership table
     * @param float $suspectAfter Seconds before a missing node becomes SUSPECT
     * @param float $downAfter Seconds before a missing node becomes DOWN
     */
    public function __construct(
        string $localNodeId,
        int $maxNodes = 64,
        float $suspectAfter = self::DEFAULT_SUSPECT_AFTER,
        float $downAfter = self::DEFAULT_DOWN_AFTER
    ) {
        $this->localNodeId = $localNodeId;
        $this->suspectAfter = $suspectAfter;
        $this->downAfter = $downAfter;

        $this->memberTable = new Table($maxNodes);
        $this->memberTable->column('nodeId', Table::TYPE_STRING, 64);
        $this->memberTable->column('host', Table::TYPE_STRING, 64);
        $this->memberTable->column('port', Table::TYPE_INT);
        $this->memberTable->column('weight', Table::TYPE_INT);
        $this->memberTable->column('status', Table::TYPE_STRING, 16);
        $this->memberTable->column('lastHeartbeat', Table::TYPE_INT);
        $this->memberTable->column('incarnation', Table::TYPE_INT);
        $this->memberTable->create();
    }

    /**
     * Register (or re-register) the local node. Called once per process start.
     */
    public function join(string $host, int $port, int $weight = 1): void
    {
        $now = time();
        $existing = $this->memberTable->get($this->localNodeId);
        $incarnation = $existing === false ? 1 : ((int) $existing['incarnation']) + 1;

        $member = new ClusterMember(
            $this->localNodeId, $host, $port, $weight,
            ClusterMember::STATUS_UP, $now, $incarnation
        );
        $this->memberTable->set($this->localNodeId, $member->toRow());
    }

    /**
     * Announce graceful departure (optional; DOWN is also implied by timeout).
     */
    public function leave(): void
    {
        $row = $this->memberTable->get($this->localNodeId);
        if ($row !== false) {
            $row['status'] = ClusterMember::STATUS_DOWN;
            $this->memberTable->set($this->localNodeId, $row);
        }
    }

    /**
     * Periodic maintenance: heartbeat + failure detection. Returns the set of
     * node ids whose status changed so the caller can trigger rebalancing.
     *
     * @return string[] Node ids that changed status since the previous tick
     */
    public function tick(): array
    {
        $now = time();
        $changed = [];

        // Self heartbeat.
        $self = $this->memberTable->get($this->localNodeId);
        if ($self !== false) {
            $self['lastHeartbeat'] = $now;
            $self['status'] = ClusterMember::STATUS_UP;
            $this->memberTable->set($this->localNodeId, $self);
        }

        foreach ($this->memberTable as $nodeId => $row) {
            if ($nodeId === $this->localNodeId) {
                continue;
            }
            $elapsed = $now - (int) $row['lastHeartbeat'];
            $prevStatus = $row['status'];

            if ($prevStatus === ClusterMember::STATUS_DOWN) {
                continue;
            }

            if ($elapsed >= $this->downAfter) {
                $row['status'] = ClusterMember::STATUS_DOWN;
                $this->memberTable->set($nodeId, $row);
                $changed[] = $nodeId;
            } elseif ($elapsed >= $this->suspectAfter) {
                $row['status'] = ClusterMember::STATUS_SUSPECT;
                $this->memberTable->set($nodeId, $row);
                if ($prevStatus !== ClusterMember::STATUS_SUSPECT) {
                    $changed[] = $nodeId;
                }
            }
        }

        if (!empty($changed)) {
            $this->notify($changed);
        }

        return $changed;
    }

    /**
     * Record a heartbeat received from a peer (used when a real transport exists;
     * for the in-process Table build this is a no-op-friendly hook).
     */
    public function observe(ClusterMember $member): void
    {
        $row = $this->memberTable->get($member->nodeId);
        if ($row !== false && (int) $row['incarnation'] > $member->incarnation) {
            return; // stale update, ignore
        }
        $member->lastHeartbeat = time();
        if ($member->status === ClusterMember::STATUS_SUSPECT && $row !== false) {
            $member->status = ClusterMember::STATUS_UP;
        }
        $this->memberTable->set($member->nodeId, $member->toRow());
    }

    /**
     * @return ClusterMember[] Only nodes considered reachable (UP).
     */
    public function aliveNodes(): array
    {
        $out = [];
        foreach ($this->memberTable as $nodeId => $row) {
            $member = ClusterMember::fromRow($row);
            if ($member->isAlive()) {
                $out[$nodeId] = $member;
            }
        }
        return $out;
    }

    /**
     * @return ClusterMember[] All known nodes regardless of status.
     */
    public function allNodes(): array
    {
        $out = [];
        foreach ($this->memberTable as $nodeId => $row) {
            $out[$nodeId] = ClusterMember::fromRow($row);
        }
        return $out;
    }

    public function getNode(string $nodeId): ?ClusterMember
    {
        $row = $this->memberTable->get($nodeId);
        return $row === false ? null : ClusterMember::fromRow($row);
    }

    public function isLocal(string $nodeId): bool
    {
        return $nodeId === $this->localNodeId;
    }

    public function getLocalNodeId(): string
    {
        return $this->localNodeId;
    }

    /**
     * Register a listener invoked with the list of changed node ids after each tick.
     */
    public function registerListener(callable $cb): void
    {
        $this->listeners[] = $cb;
    }

    private function notify(array $changed): void
    {
        foreach ($this->listeners as $cb) {
            $cb($changed, $this);
        }
    }
}
