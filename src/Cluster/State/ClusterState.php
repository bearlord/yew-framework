<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

use Yew\Cluster\ClusterConfig;
use Yew\Cluster\Router\GossipShardRouter;
use Yew\Cluster\Transport\GossipTransport;
use Yew\Cluster\Transport\UdpGossipTransport;
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
 * Membership / FD / gossip are powered by a mounted {@see GossipClusterState}
 * engine running inside this same process. A self-managed {@see UdpGossipTransport}
 * (setManaged(false)) opens the UDP socket in THIS process, so the entire gossip
 * protocol — handshake, ACK, fragmentation, key verification and the FD ticker —
 * executes in the single cluster-state process. There is no Swoole\Table and no
 * multi-worker gossip: this process is the sole authority.
 *
 * The earlier Stage-1 static-peer seeding (PeerState) is retained only as a
 * zero-gossip fallback: if no gossip engine is attached, locate() falls back to
 * a simple consistent-hash ring over the static peers. Once attachGossip() is
 * called, the engine fully owns membership and the static ring is ignored.
 */
class ClusterState implements ClusterStateInterface
{
    /**
     * @var string Local node id
     */
    private string $localNodeId;

    /**
     * @var GossipClusterState|null The real gossip engine (stage 1.5+). When set,
     *      it owns membership, FD and (via its transport) the UDP wire.
     */
    private ?GossipClusterState $gossip = null;

    /**
     * @var GossipShardRouter|null Consistent-hash router built over the engine.
     */
    private ?GossipShardRouter $router = null;

    /**
     * @var array<int, callable> Listeners fired when the view changes (forwarded
     *      to the engine when present, else served by the fallback ring).
     */
    private array $listeners = [];

    /**
     * @var callable|null Returns the current epoch seconds (injectable for tests)
     */
    private $nowFn;

    /**
     * @var bool When true (Stage 1: static peer seeding, no gossip yet), peers
     *           that were seeded optimistically are held UP regardless of silence,
     *           because nothing is sending heartbeats to drive the FD verdict.
     */
    private bool $optimisticPeers;

    // ---- Stage-1 fallback (used only when no gossip engine is attached) ----
    /**
     * @var array<string, PeerState> Static peer ring for the no-gossip fallback.
     */
    private array $fallback = [];

    /**
     * @param string $localNodeId
     * @param int    $suspectAfter
     * @param int    $downAfter
     * @param array  $peers        Static seed peers as ["nodeId" => "host:port"]
     * @param callable|null $nowFn
     * @param bool   $optimisticPeers  Hold seeded peers UP until gossip takes over
     */
    public function __construct(
        string $localNodeId = '',
        int $suspectAfter = 0,
        int $downAfter = 0,
        array $peers = [],
        ?callable $nowFn = null,
        bool $optimisticPeers = false
    ) {
        $this->localNodeId = $localNodeId;
        $this->nowFn = $nowFn ?? static fn () => time();
        $this->optimisticPeers = $optimisticPeers;

        // Seed the Stage-1 fallback ring (local + static peers). Ignored once a
        // real gossip engine is attached via attachGossip().
        $this->fallback[$localNodeId] = new PeerState($localNodeId, '127.0.0.1', 0, true);
        foreach ($peers as $peerId => $endpoint) {
            [$host, $port] = explode(':', (string) $endpoint) + ['', 0];
            $this->fallback[(string) $peerId] = new PeerState(
                (string) $peerId, (string) $host, (int) $port, false
            );
        }
        // Keep the legacy thresholds available for the engine hand-off.
        $this->suspectAfter = $suspectAfter;
        $this->downAfter = $downAfter;
    }

    private int $suspectAfter;
    private int $downAfter;

    /**
     * Mount the real gossip engine into this process and wire it up: attach the
     * routing interface, open the self-managed UDP socket, spawn the receiver
     * coroutine and send the initial SYNs to seeds. After this call the engine
     * fully owns membership / FD / the wire; the Stage-1 fallback ring is ignored.
     *
     * Must be called exactly once, inside the cluster-state process.
     *
     * @param ClusterConfig        $cfg
     * @param GossipClusterState   $engine   Pre-built, keyed + joined engine
     * @param UdpGossipTransport   $udp      Self-managed transport (setManaged(false))
     * @param GossipShardRouter    $router   Router over $engine
     * @param string[]             $seeds    Seed endpoints host:port
     * @param string|null          $peerCacheFile  Path to persist learned peers for seed self-healing
     */
    public function attachGossip(
        ClusterConfig $cfg,
        GossipClusterState $engine,
        UdpGossipTransport $udp,
        GossipShardRouter $router,
        array $seeds,
        ?string $peerCacheFile = null
    ): void {
        $this->gossip = $engine;
        $this->router = $router;
        // Forward any listeners registered before attach to the engine.
        foreach ($this->listeners as $cb) {
            $engine->registerListener($cb);
        }
        // Seed self-healing: persist learned peers so a cold start can re-join
        // even when every static seed is down.
        if ($peerCacheFile !== null) {
            $engine->setPeerCacheFile($peerCacheFile);
        }
        // Engine drives FD and reconciliation; no shared table needed here.
        $engine->start($udp, $seeds);
    }

    /**
     * Whether the real gossip engine is mounted (stage 1.5+).
     */
    public function hasGossip(): bool
    {
        return $this->gossip !== null;
    }

    public function now(): int
    {
        return ($this->nowFn)();
    }

    // ---------------------------------------------------------------------
    // ClusterStateInterface — delegate to the engine, fall back to the ring.
    // ---------------------------------------------------------------------

    /**
     * @return ClusterMember[]
     */
    public function aliveNodes(): array
    {
        if ($this->gossip !== null) {
            return $this->gossip->aliveNodes();
        }
        $out = [];
        foreach ($this->fallback as $id => $p) {
            if ($p->status === PeerState::UP) {
                $out[$id] = new ClusterMember(
                    $id, $p->host, $p->port, $p->local,
                    ClusterMember::STATUS_UP, $p->lastHeartbeat
                );
            }
        }
        return $out;
    }

    public function getNode(string $nodeId): ?ClusterMember
    {
        if ($this->gossip !== null) {
            return $this->gossip->getNode($nodeId);
        }
        $p = $this->fallback[$nodeId] ?? null;
        if ($p === null) {
            return null;
        }
        return new ClusterMember(
            $nodeId, $p->host, $p->port, $p->local,
            $p->status === PeerState::UP ? ClusterMember::STATUS_UP : ClusterMember::STATUS_DOWN,
            $p->lastHeartbeat
        );
    }

    public function isLocal(string $nodeId): bool
    {
        return $nodeId === $this->localNodeId;
    }

    public function registerListener(callable $cb): void
    {
        $this->listeners[] = $cb;
        if ($this->gossip !== null) {
            $this->gossip->registerListener($cb);
        }
    }

    // ---------------------------------------------------------------------
    // Routing / FD / IPC accessors
    // ---------------------------------------------------------------------

    /**
     * Resolve the owning node of an actor name.
     */
    public function locate(string $actorName): ?Location
    {
        if ($this->router !== null) {
            return $this->router->locate($actorName);
        }
        // No-gossip fallback: consistent-hash ring over static peers.
        $owner = $this->fallbackOwner($actorName);
        if ($owner === null) {
            return null;
        }
        $p = $this->fallback[$owner];
        $node = new ClusterNode($owner, $p->host, $p->port, $p->local);
        return new Location($node, 0);
    }

    /**
     * Serializable (array) form of locate() for IPC return values.
     * @return array{nodeId:string,host:string,port:int,local:bool,processId:int}|null
     */
    public function getLocationArray(string $actorName): ?array
    {
        $loc = $this->locate($actorName);
        if ($loc === null) {
            return null;
        }
        $node = $loc->getNode();
        return [
            'nodeId' => $node->getNodeId(),
            'host' => $node->getHost(),
            'port' => $node->getPort(),
            'local' => $node->isLocal(),
            'processId' => $loc->getProcessId(),
        ];
    }

    /**
     * IPC-friendly snapshot of the full member view.
     * @return array<string,array{nodeId:string,host:string,port:int,local:bool,status:string,lastHeartbeat:int}>
     */
    public function getMemberView(): array
    {
        if ($this->gossip !== null) {
            $members = $this->gossip->allNodes();
        } else {
            $members = [];
            foreach ($this->fallback as $id => $p) {
                $members[$id] = new ClusterMember(
                    $id, $p->host, $p->port, $p->local,
                    $p->status === PeerState::UP ? ClusterMember::STATUS_UP : ClusterMember::STATUS_DOWN,
                    $p->lastHeartbeat
                );
            }
        }
        $out = [];
        foreach ($members as $id => $m) {
            $out[$id] = [
                'nodeId' => $m->nodeId,
                'host' => $m->host,
                'port' => $m->port,
                'local' => $m->local,
                'status' => $m->status,
                'lastHeartbeat' => $m->lastHeartbeat,
            ];
        }
        return $out;
    }

    /**
     * Record a heartbeat for a peer (used by the gossip engine on inbound SYN-ACK).
     * No-op in the no-gossip fallback (the FD tick keeps static peers UP).
     */
    public function heartbeat(string $nodeId, int $ts): void
    {
        if ($this->gossip !== null) {
            return; // engine manages its own member heartbeats
        }
        if (isset($this->fallback[$nodeId])) {
            $this->fallback[$nodeId]->lastHeartbeat = $ts;
        }
    }

    /**
     * Inject the local ClusterActorStore so the engine can ingest replicas
     * straight to disk on inbound STORE_PUT (used only inside the cluster-state
     * process, where the real engine + UDP wire live).
     */
    public function setActorStore(object $store): void
    {
        if ($this->gossip !== null) {
            $this->gossip->setActorStore($store);
        }
    }

    /**
     * Register the cross-node supervision callback fired when a peer node goes
     * DOWN. The engine drives FD, so we forward to it.
     *
     * @param callable(string):void $cb
     */
    public function onNodeDown(callable $cb): void
    {
        if ($this->gossip !== null) {
            $this->gossip->onNodeDown($cb);
        }
    }

    /**
     * Replicate a store mutation to peers. No-op in the no-gossip fallback.
     */
    public function replicateStoreEntry(string $actorName, string $kind, string $payload, int $ts): void
    {
        if ($this->gossip !== null) {
            $this->gossip->replicateStoreEntry($actorName, $kind, $payload, $ts);
        }
    }

    /**
     * Look up a replicated copy of a store entry. No-op (null) in fallback.
     */
    public function findReplica(string $actorName, string $kind): ?string
    {
        if ($this->gossip === null) {
            return null;
        }
        return $this->gossip->findReplica($actorName, $kind);
    }

    /**
     * Actor names replicated from a dead $ownerNodeId — drives cross-node
     * failover on the surviving worker nodes.
     *
     * @return string[]
     */
    public function getFailoverActors(string $ownerNodeId): array
    {
        if ($this->gossip === null) {
            return [];
        }
        return $this->gossip->getReplicatedActorNames($ownerNodeId);
    }

    /**
     * Failure-detection tick. When the gossip engine is mounted it owns FD; we
     * just forward. In the no-gossip fallback we keep static peers UP (optimistic)
     * so the ring stays valid for architecture verification.
     *
     * @return string[] node ids whose status changed
     */
    public function tick(): array
    {
        if ($this->gossip !== null) {
            return $this->gossip->tick();
        }
        if ($this->optimisticPeers) {
            return [];
        }
        $now = $this->now();
        $changed = [];
        foreach ($this->fallback as $id => $p) {
            if ($p->local) {
                continue;
            }
            $silent = $now - $p->lastHeartbeat;
            if ($silent >= $this->downAfter) {
                if ($p->status !== PeerState::DOWN) {
                    $p->status = PeerState::DOWN;
                    $changed[] = $id;
                }
            } elseif ($silent >= $this->suspectAfter) {
                if ($p->status !== PeerState::SUSPECT) {
                    $p->status = PeerState::SUSPECT;
                    $changed[] = $id;
                }
            }
        }
        if (!empty($changed)) {
            foreach ($this->listeners as $cb) {
                $cb($changed, $this);
            }
        }
        return $changed;
    }

    // ---------------------------------------------------------------------
    // No-gossip fallback ring (consistent hash over static peers)
    // ---------------------------------------------------------------------

    private function fallbackOwner(string $actorName): ?string
    {
        $alive = [];
        foreach ($this->fallback as $id => $p) {
            if ($p->status === PeerState::UP) {
                $alive[$id] = $p;
            }
        }
        if ($alive === []) {
            return null;
        }
        $key = $this->hash($actorName);
        $best = null;
        $bestDelta = null;
        foreach ($alive as $id => $p) {
            $h = $this->hash($id);
            $delta = $h >= $key ? ($h - $key) : (0xFFFFFFFF - $key + $h);
            if ($bestDelta === null || $delta < $bestDelta) {
                $bestDelta = $delta;
                $best = $id;
            }
        }
        return $best;
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
