<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor;

use Yew\Cluster\Router\IpcShardRouter;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Persistence\FileActorStore;

/**
 * Cross-node actor failover driver.
 *
 * Runs inside the actor process (the ONLY place where ActorManager::addActor
 * is legal). Once the cluster-state process marks a peer node DOWN, every
 * replicated copy of that node's actors is buffered in this node's
 * cluster-state process (ClusterActorStore::findReplica, reachable over IPC).
 *
 * On each tick we:
 *   1. pull the authoritative member view and rebuild the local ring,
 *   2. for every DOWN node, list the actors replicated from it,
 *   3. for each one that the consistent-hash ring now maps to THIS node and is
 *      not already live, recreate it from the replicated class meta + replay
 *      its durable state via recovery().
 *
 * This keeps the single-writer authority: the actual replica buffer still
 * lives only in the cluster-state process; this class merely reads it over IPC
 * and (re)spawns the surviving actors. No per-process divergence is possible.
 */
class ActorFailover
{
    public function __construct(
        private IpcShardRouter $router,
        private ClusterActorStore $store,
        private string $processName
    ) {
    }

    /**
     * One failover sweep. Returns the list of actor names (re)spawned.
     *
     * @return string[]
     */
    public function run(): array
    {
        // refresh() already pulls the member view over IPC and caches it; reuse
        // that cache via getView() instead of paying for a second IPC round-trip.
        $this->router->refresh();
        $view = $this->router->getView();
        if ($view === []) {
            return [];
        }
        $localNodeId = $this->router->getLocalNode()->getNodeId();
        $spawned = [];

        foreach ($view as $nodeId => $row) {
            if (($row['status'] ?? '') !== 'DOWN') {
                continue;
            }
            $candidates = $this->router->clusterFailoverActors($nodeId);
            if ($candidates === []) {
                continue;
            }
            foreach ($candidates as $name) {
                if ($this->spawnIfOwned($name, $localNodeId)) {
                    $spawned[] = $name;
                }
            }
        }
        return $spawned;
    }

    /**
     * Spawn $name locally when the ring now owns it and it is not live yet.
     */
    private function spawnIfOwned(string $name, string $localNodeId): bool
    {
        $actorManager = ActorManager::getInstance();
        if ($actorManager->getActor($name) instanceof Actor) {
            return false; // already live
        }
        $owner = $this->router->ownerOf($name);
        if ($owner !== $localNodeId) {
            return false; // belongs to another surviving node
        }
        $class = $this->store->loadClass($name);
        if ($class === null || !class_exists($class)) {
            return false;
        }
        try {
            /** @var Actor $actor */
            $actor = new $class($name, true);
            $actor->recovery();
            $actorManager->addActor($actor);
            Server::$instance->getLog()->info(
                "cluster: failover spawned actor [$name] (class $class) on {$this->processName}"
            );
            return true;
        } catch (\Throwable $e) {
            Server::$instance->getLog()->warning(
                "cluster: failover spawn of [$name] failed: " . $e->getMessage()
            );
            return false;
        }
    }
}
