<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Router;

use Yew\Plugins\Actor\ActorManager;

/**
 * Single-machine shard router.
 *
 * Reads actor locations from the local shared-memory table kept by
 * {@see ActorManager}. Every actor is considered to live on the local node;
 * the worker process id is taken from the actor table. This keeps the exact
 * behaviour the framework had before clustering, but behind the
 * {@see ShardRouter} interface so a distributed implementation can be swapped
 * in later without changing call sites.
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
        $manager = ActorManager::getInstance();
        $data = $manager->getActorRaw($actorName);
        if (empty($data)) {
            return null;
        }

        return new Location($this->localNode, (int) $data['processId']);
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
