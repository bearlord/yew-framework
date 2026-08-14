<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Router;

use Yew\Cluster\GetClusterState;
use Yew\Cluster\State\ClusterNode;
use Yew\Cluster\State\Location;

/**
 * Shard router that resolves actor placement through the authoritative
 * "cluster-state" helper process over IPC (see {@see GetClusterState}).
 *
 * The consistent-hash ring is recomputed locally from the member view returned
 * by the cluster-state process, so every worker routes identically (no
 * per-worker disagreement, no shared Swoole\Table). Membership ownership lives
 * entirely in the single cluster-state process; this class is a stateless
 * read-through proxy + a local ring cache that is refreshed on a timer so
 * topology rebalancing (actor eviction) still works inside the worker.
 */
class IpcShardRouter implements ShardRouter
{
    use GetClusterState;

    private ClusterNode $localNode;
    private int $replicas;

    /** @var array<string,array{nodeId:string,host:string,port:int,local:bool,status:string}> */
    private array $view = [];

    /** @var array<int,string> hash => nodeId */
    private array $ring = [];

    /** @var callable|null */
    private $rebalanceHook = null;

    /** @var array<string,string>|null last seen nodeId => status, to detect membership/status changes */
    private ?array $lastStatuses = null;

    public function __construct(ClusterNode $localNode, int $replicas = 128)
    {
        $this->localNode = $localNode;
        $this->replicas = $replicas;
        // Do NOT refresh here: the cluster-state process is not ready during
        // beforeServerStart (DI registration time). The ring is populated lazily
        // on first refresh(), driven by a worker timer started in ClusterPlugin.
    }

    public function getLocalNode(): ClusterNode
    {
        return $this->localNode;
    }

    /**
     * Resolve the owning node via the cluster-state process (authoritative).
     */
    public function locate(string $actorName): ?Location
    {
        return $this->clusterLocate($actorName);
    }

    /**
     * Which node should host the actor, per the locally cached ring.
     */
    public function ownerOf(string $actorName): ?string
    {
        if (empty($this->ring)) {
            return null;
        }
        $key = $this->hash($actorName);
        $hashes = array_keys($this->ring);
        sort($hashes);
        foreach ($hashes as $h) {
            if ($h >= $key) {
                return $this->ring[$h];
            }
        }
        return $this->ring[$hashes[0]];
    }

    /**
     * Pull the latest member view from the cluster-state process and rebuild the
     * local ring. Fires the rebalance hook when the membership set changed.
     *
     * @return string[] node ids whose status changed since the last refresh
     */
    public function refresh(): array
    {
        $view = $this->getClusterView();
        $statuses = [];
        foreach ($view as $id => $row) {
            $statuses[$id] = $row['status'] ?? 'unknown';
        }
        // "Changed" = first run, or any node added/removed, or any status flip
        // (e.g. a peer going DOWN must trigger rebalance + failover candidates).
        $changed = [];
        if ($this->lastStatuses === null) {
            $changed = array_keys($view);
        } else {
            foreach ($statuses as $id => $st) {
                if (!array_key_exists($id, $this->lastStatuses) || $this->lastStatuses[$id] !== $st) {
                    $changed[] = $id;
                }
            }
            // nodes that disappeared entirely
            foreach ($this->lastStatuses as $id => $_) {
                if (!array_key_exists($id, $statuses)) {
                    $changed[] = $id;
                }
            }
        }
        $this->view = $view;
        $this->lastStatuses = $statuses;
        $this->rebuild();
        if ($changed !== [] && $this->rebalanceHook !== null) {
            ($this->rebalanceHook)($changed, $this);
        }
        return $changed;
    }

    public function onRebalance(callable $hook): void
    {
        $this->rebalanceHook = $hook;
    }

    public function register(string $actorName, Location $location): void
    {
        // Placement is derived from the ring owned by the cluster-state process;
        // the actor layer keeps the authoritative process id in its own table.
        // No-op seam (matches GossipShardRouter).
    }

    public function unregister(string $actorName): void
    {
        // No-op; ownership recomputed from the ring on lookup.
    }

    private function rebuild(): void
    {
        $this->ring = [];
        foreach ($this->view as $id => $row) {
            $r = max(1, (int) round($this->replicas * ($row['weight'] ?? 1)));
            for ($i = 0; $i < $r; $i++) {
                $h = $this->hash($id . '#' . $i);
                $this->ring[$h] = $id;
            }
        }
        ksort($this->ring);
    }

    private function hash(string $s): int
    {
        $hash = 2166136261;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $hash ^= ord($s[$i]);
            $hash = ($hash * 16777619) & 0xFFFFFFFF;
        }
        return (int) $hash;
    }
}
