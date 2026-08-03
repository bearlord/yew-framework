<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

use Yew\Plugins\Actor\ActorManager;

/**
 * Location-transparent shard router backed by consistent hashing over the
 * live cluster membership.
 *
 * Actor names are hashed onto a virtual ring; each segment of the ring is
 * owned by the alive node whose token is the first clockwise match. When the
 * membership changes (node up/down detected by {@see ClusterState}), the set of
 * owned shards changes and {@see onMembershipChange} reconstructs the ring and
 * notifies a rebalance callback — this is exactly the "rebalance" half of
 * cluster sharding (Akka: coordinator + shard allocation; Orleans: silo
 * activation + grain placement).
 *
 * Placement (create) vs. resolution (locate) both consult the current ring, so
 * actor-to-node assignment stays stable across the cluster without a central
 * coordinator.
 */
class GossipShardRouter implements ShardRouter
{
    private ClusterStateInterface $cluster;
    private ClusterNode $localNode;
    private int $replicas;
    private array $ring = [];          // hash => nodeId
    private array $tokens = [];        // nodeId => [hashes]
    private ?array $aliveCache = null;
    /** @var callable|null */
    private $rebalanceHook = null;

    public function __construct(
        ClusterStateInterface $cluster,
        ClusterNode $localNode,
        int $replicas = 128
    ) {
        $this->cluster = $cluster;
        $this->localNode = $localNode;
        $this->replicas = $replicas;
        $this->rebuild();
        $this->cluster->registerListener(function (array $changed) {
            $this->rebuild();
            if ($this->rebalanceHook) {
                ($this->rebalanceHook)($changed, $this);
            }
        });
    }

    /**
     * Called by the cluster layer on every membership change so the actor
     * system can migrate / evict shards that no longer belong to this node.
     */
    public function onRebalance(callable $hook): void
    {
        $this->rebalanceHook = $hook;
    }

    /**
     * Shards (ring segments) currently owned by this node.
     *
     * @return string[] Actor-name hash keys owned locally
     */
    public function ownedShards(): array
    {
        $owned = [];
        foreach ($this->ring as $hash => $nodeId) {
            if ($nodeId === $this->localNode->getNodeId()) {
                $owned[] = (string) $hash;
            }
        }
        return $owned;
    }

    public function locate(string $actorName): ?Location
    {
        $owner = $this->ownerOf($actorName);
        if ($owner === null) {
            return null;
        }
        $member = $this->cluster->getNode($owner);
        if ($member === null) {
            return null;
        }
        $node = new ClusterNode(
            $member->nodeId, $member->host, $member->port,
            $this->cluster->isLocal($member->nodeId)
        );
        // Process id is still resolved from the local actor table when the
        // owning node is this one; remote placement needs a real transport.
        $processId = 0;
        if ($node->isLocal()) {
            $data = ActorManager::getInstance()->getActorRaw($actorName);
            $processId = $data !== null ? (int) $data['processId'] : 0;
        }
        return new Location($node, $processId);
    }

    public function register(string $actorName, Location $location): void
    {
        // Placement is derived from the ring; the local actor table still holds
        // the authoritative process id (see ActorManager::addActor). No-op seam.
    }

    public function unregister(string $actorName): void
    {
        // No-op; ownership recomputed from the ring on lookup.
    }

    public function getLocalNode(): ClusterNode
    {
        return $this->localNode;
    }

    /**
     * Which node should host the given actor name, per consistent hashing.
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
        return $this->ring[$hashes[0]]; // wrap around
    }

    private function rebuild(): void
    {
        $this->ring = [];
        $this->tokens = [];
        foreach ($this->cluster->aliveNodes() as $member) {
            $this->addNode($member);
        }
        ksort($this->ring);
    }

    private function addNode(ClusterMember $member): void
    {
        $this->tokens[$member->nodeId] = [];
        // Weight scales virtual replicas so stronger nodes get more shards.
        $r = max(1, (int) round($this->replicas * $member->weight));
        for ($i = 0; $i < $r; $i++) {
            $h = $this->hash($member->nodeId . '#' . $i);
            $this->ring[$h] = $member->nodeId;
            $this->tokens[$member->nodeId][] = $h;
        }
    }

    private function hash(string $s): int
    {
        // FNV-1a 32-bit, unsigned.
        $hash = 2166136261;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $hash ^= ord($s[$i]);
            $hash = ($hash * 16777619) & 0xFFFFFFFF;
        }
        return (int) $hash;
    }
}
