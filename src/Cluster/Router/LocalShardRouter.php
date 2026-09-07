<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Router;

use Yew\Cluster\State\ClusterNode;
use Yew\Cluster\State\Location;

/**
 * Single-machine shard router.
 *
 * Treats every actor as living on the local node. The worker process id for a
 * located actor is supplied through an injected locator callback (set by the
 * actor layer, which owns the actor table) so this router stays free of any
 * actor-package dependency. Exposes the {@see ShardRouter} interface so a
 * distributed implementation can be swapped in without changing call sites.
 */
class LocalShardRouter implements ShardRouter
{
    private ClusterNode $localNode;

    /**
     * Build a local-only router for one node.
     *
     * @param string $nodeId Id of the single local node
     */
    public function __construct(string $nodeId = 'local')
    {
        $this->localNode = new ClusterNode($nodeId, '127.0.0.1', 0, true);
    }

    public function locate(string $actorName): ?Location
    {
        // Always local. The worker process id is resolved by the actor layer via
        // its own actor table, so the router stays cluster-only and actor-agnostic.
        return new Location($this->localNode, 0);
    }

    public function register(string $actorName, Location $location): void
    {
        // Local placement is already recorded by ActorManager::addActor.
        // Kept as a no-op seam for the clustered implementation.
    }

    public function unregister(string $actorName): void
    {
        // Local removal is already handled by ActorManager::removeActor.
    }

    public function getLocalNode(): ClusterNode
    {
        return $this->localNode;
    }
}
