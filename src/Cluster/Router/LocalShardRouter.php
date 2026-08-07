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
     * @var callable(string):?array|null Injected actor-row lookup.
     */
    private $actorLocator = null;

    /**
     * Build a local-only router for one node.
     *
     * @param string $nodeId Id of the single local node
     */
    public function __construct(string $nodeId = 'local')
    {
        $this->localNode = new ClusterNode($nodeId, '127.0.0.1', 0, true);
    }

    /**
     * Inject the actor-row lookup used by {@see locate()}. The callback receives
     * an actor name and returns the actor table row (with a "processId" key) or
     * null. Owned by the actor layer; defaults to "not found".
     *
     * @param callable(string):?array $fn
     */
    public function setActorLocator(callable $fn): void
    {
        $this->actorLocator = $fn;
    }

    public function locate(string $actorName): ?Location
    {
        if ($this->actorLocator === null) {
            return null;
        }
        $data = ($this->actorLocator)($actorName);
        if (empty($data)) {
            return null;
        }

        return new Location($this->localNode, (int) ($data['processId'] ?? 0));
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
