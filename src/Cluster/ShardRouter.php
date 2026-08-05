<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

/**
 * Resolves an actor name to its physical {@see Location}.
 *
 * This is the clustering seam. Today the only implementation is
 * {@see LocalShardRouter}, which reads the local shared-memory actor table.
 * A future clustered implementation (e.g. GossipShardRouter) would consult a
 * distributed shard map populated via gossip/consensus, enabling automatic
 * rebalancing and location transparency without touching Actor or Proxy code.
 */
interface ShardRouter
{
    /**
     * The node this router instance belongs to (used to know which shards are
     * "local" and therefore hostable by this process).
     */
    public function getLocalNode(): ClusterNode;

    /**
     * Locate an actor by name.
     *
     * @param string $actorName
     * @return Location|null Null when the actor does not (yet) exist anywhere
     */
    public function locate(string $actorName): ?Location;

    /**
     * Register an actor's location (called on creation).
     *
     * @param string   $actorName
     * @param Location $location
     */
    public function register(string $actorName, Location $location): void;

    /**
     * Forget an actor's location (called on termination).
     *
     * @param string $actorName
     */
    public function unregister(string $actorName): void;
}
