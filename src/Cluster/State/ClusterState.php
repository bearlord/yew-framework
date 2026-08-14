<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

use Yew\Core\Memory\CrossProcess\Table;

/**
 * Authority for cluster membership, failure detection and shard routing.
 *
 * This object lives inside a SINGLE dedicated helper process ("cluster-state",
 * one per node). Because it is the only writer and reader of its member table,
 * there is no cross-worker shared state and therefore no split-brain: failure
 * detection and the consistent-hash ring are computed exactly once per node.
 *
 * Worker processes (including actor workers) never touch this state directly;
 * they query it through the {@see \Yew\Cluster\GetClusterState} IPC proxy, which
 * forwards method calls to this instance over the process message channel.
 *
 * Stage 1 (this class) seeds the member view from configuration (local node +
 * static peers) and runs the in-process failure-detection tick. The gossip UDP
 * protocol will be moved here in a later stage; until then peers are trusted to
 * be present and their liveness is tracked by the tick against lastHeartbeat
 * (which, in stage 1, is only updated by external heartbeats once gossip lands,
 * or simply assumed alive until downAfter elapses — see {@see tick}).
 */
class ClusterState implements ClusterStateInterface
{
    /**
     * @var string Local node id
     */
    private string $localNodeId;

    /**
     * @var array<string, ClusterMember> Authoritative member table (process memory).
     */
    private array $members = [];

    /**
     * @var int Seconds before a silent peer is marked suspect
     */
    private int $suspectAfter;

    /**
     * @var int Seconds before a silent peer is marked down
     */
    private int $downAfter;

    /**
     * @var array<int, callable> Listeners fired when the view changes
     */
    private array $listeners = [];

    /**
     * @var callable|null Returns the current epoch seconds (injectable for tests)
     */
    private $nowFn;

    /**
     * @param string $localNodeId
     * @param int    $suspectAfter
     * @param int    $downAfter
     * @param array  $peers        Static seed peers as ["nodeId" => "host:port"]
     * @param callable|null $nowFn
     */
    public function __construct(
        string $localNodeId,
        int $suspectAfter,
        int $downAfter,
        array $peers = [],
        ?callable $nowFn = null
    ) {
        $this->localNodeId = $localNodeId;
        $this->suspectAfter = $suspectAfter;
        $this->downAfter = $downAfter;
        $this->nowFn = $nowFn ?? static fn () => time();

        // Seed: local node is always up and owned by this process.
        $this->members[$localNodeId] = new ClusterMember(
            $localNodeId, '127.0.0.1', 0, true, ClusterMember::STATUS_UP, $this->now()
        );
        // Static peers start as "up" (optimistic); the FD tick will downgrade
        // them if no heartbeat arrives. Once gossip is wired in stage 1.5 this
        // seeding is replaced by discovered membership.
        foreach ($peers as $peerId => $endpoint) {
            [$host, $port] = explode(':', (string) $endpoint) + ['', 0];
            $this->members[(string) $peerId] = new ClusterMember(
                (string) $peerId, (string) $host, (int) $port, false,
                ClusterMember::STATUS_UP, $this->now()
            );
        }
    }

    private function now(): int
    {
        return ($this->nowFn)();
    }

    /**
     * Failure-detection tick. Runs ONLY inside the cluster-state process, so the
     * verdict is authoritative and consistent — no per-worker disagreement.
     *
     * @return void
     */
    public function tick(): void
    {
        $changed = false;
        $now = $this->now();
        foreach ($this->members as $id => $m) {
            if ($m->isLocal()) {
                continue; // local is always alive
            }
            $silent = $now - $m->lastHeartbeat;
            $prev = $m->status;
            if ($silent >= $this->downAfter) {
                $m->status = ClusterMember::STATUS_DOWN;
            } elseif ($silent >= $this->suspectAfter) {
                $m->status = ClusterMember::STATUS_SUSPECT;
            }
            if ($m->status !== $prev) {
                $changed = true;
            }
        }
        if ($changed) {
            $this->fireListeners();
        }
    }

    /**
     * Record a heartbeat for a node (called by gossip receive in later stage, or
     * by an external probe). Keeps the member alive.
     *
     * @param string $nodeId
     * @param int|null $ts
     * @return void
     */
    public function heartbeat(string $nodeId, ?int $ts = null): void
    {
        if (!isset($this->members[$nodeId])) {
            return;
        }
        $this->members[$nodeId]->lastHeartbeat = $ts ?? $this->now();
        if ($this->members[$nodeId]->status !== ClusterMember::STATUS_UP) {
            $this->members[$nodeId]->status = ClusterMember::STATUS_UP;
            $this->fireListeners();
        }
    }

    /**
     * Resolve the owning node of an actor by consistent-hash ring over the
     * currently-UP members. Peers that are suspect/down participate only if no
     * UP member exists, to keep the ring stable during transient partitions.
     *
     * @param string $actorName
     * @return Location|null
     */
    public function locate(string $actorName): ?Location
    {
        $up = [];
        $fallback = [];
        foreach ($this->members as $m) {
            if ($m->status === ClusterMember::STATUS_UP) {
                $up[$m->nodeId] = $m;
            } else {
                $fallback[$m->nodeId] = $m;
            }
        }
        $pool = $up !== [] ? $up : $fallback;
        if ($pool === []) {
            return null;
        }
        $ring = $this->buildRing($pool);
        $key = $this->hashKey($actorName);
        $owner = $this->ownerOfRing($ring, $key);
        if ($owner === null) {
            return null;
        }
        $node = new ClusterNode(
            $owner->nodeId, $owner->host, $owner->port,
            $owner->nodeId === $this->localNodeId
        );
        return new Location($node, 0);
    }

    /**
     * Serializable snapshot of the member view for worker-side queries.
     *
     * @return array
     */
    public function getMemberView(): array
    {
        $out = [];
        foreach ($this->members as $m) {
            $out[] = [
                'nodeId'         => $m->nodeId,
                'host'           => $m->host,
                'port'           => $m->port,
                'local'          => $m->isLocal(),
                'status'         => $m->status,
                'lastHeartbeat'  => $m->lastHeartbeat,
                'alive'          => $m->status === ClusterMember::STATUS_UP || $m->isLocal(),
            ];
        }
        return $out;
    }

    /**
     * Serializable form of {@see locate()} for IPC: returns a flat array the
     * worker side can rebuild into a {@see Location}, since objects cannot cross
     * the process message channel.
     *
     * @param string $actorName
     * @return array|null [nodeId, host, port, local, processId]
     */
    public function getLocationArray(string $actorName): ?array
    {
        $loc = $this->locate($actorName);
        if ($loc === null) {
            return null;
        }
        $node = $loc->getNode();
        return [
            'nodeId'    => $node->getNodeId(),
            'host'      => $node->getHost(),
            'port'      => $node->getPort(),
            'local'     => $node->isLocal(),
            'processId' => $loc->getProcessId(),
        ];
    }

    public function getLocalNodeId(): string
    {
        return $this->localNodeId;
    }

    // ---- ClusterStateInterface ----

    /**
     * @return ClusterMember[] Only nodes considered reachable (UP).
     */
    public function aliveNodes(): array
    {
        $out = [];
        foreach ($this->members as $m) {
            if ($m->status === ClusterMember::STATUS_UP || $m->isLocal()) {
                $out[$m->nodeId] = $m;
            }
        }
        return $out;
    }

    public function getNode(string $nodeId): ?ClusterMember
    {
        return $this->members[$nodeId] ?? null;
    }

    public function isLocal(string $nodeId): bool
    {
        return isset($this->members[$nodeId]) && $this->members[$nodeId]->isLocal();
    }

    /**
     * Register a callback fired whenever the member view changes.
     *
     * @param callable $cb
     * @return void
     */
    public function registerListener(callable $cb): void
    {
        $this->listeners[] = $cb;
    }

    private function fireListeners(): void
    {
        $view = $this->getMemberView();
        foreach ($this->listeners as $cb) {
            ($cb)($view);
        }
    }

    // ---- consistent-hash ring (mirrors GossipShardRouter math) ----

    private function buildRing(array $pool): array
    {
        $ring = [];
        $replicas = 128;
        foreach ($pool as $m) {
            for ($i = 0; $i < $replicas; $i++) {
                $h = $this->hashKey($m->nodeId . '#' . $i);
                $ring[$h] = $m->nodeId;
            }
        }
        ksort($ring, SORT_NUMERIC);
        return $ring;
    }

    private function ownerOfRing(array $ring, int $key): ?ClusterMember
    {
        if ($ring === []) {
            return null;
        }
        foreach ($ring as $h => $nodeId) {
            if ($key <= $h) {
                return $this->members[$nodeId] ?? null;
            }
        }
        // wrap around
        $first = array_values($ring)[0];
        return $this->members[$first] ?? null;
    }

    private function hashKey(string $s): int
    {
        return (int) (sprintf('%u', crc32($s)) % 4294967296);
    }
}
