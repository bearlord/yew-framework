<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

use Yew\Cluster\State\ClusterState;
use Yew\Cluster\State\ClusterNode;
use Yew\Cluster\State\Location;
use Yew\Plugins\Ipc\GetIpc;

/**
 * Worker-side entry point for the authoritative cluster state.
 *
 * Forwards calls to the "cluster-state" helper process over IPC (mirrors
 * GetConnection). Because the state lives in a single dedicated process, every
 * worker sees exactly the same membership / ring — there is no per-worker
 * disagreement.
 *
 * Usage:
 *   $loc = $this->clusterLocate('my-actor');   // Location|null
 *   $view = $this->getClusterView();           // array of member rows
 */
trait GetClusterState
{
    use GetIpc;

    /**
     * Resolve the owning node of an actor via the cluster-state process.
     *
     * @param string $actorName
     * @return Location|null
     */
    public function clusterLocate(string $actorName): ?Location
    {
        $ipc = $this->callProcessName(ClusterPlugin::PROCESS_NAME, ClusterState::class);
        if ($ipc === null) {
            return null;
        }
        $row = $ipc->getLocationArray($actorName);
        if (!is_array($row)) {
            return null;
        }
        $node = new ClusterNode(
            (string) $row['nodeId'],
            (string) $row['host'],
            (int) $row['port'],
            (bool) ($row['local'] ?? false)
        );
        return new Location($node, (int) ($row['processId'] ?? 0));
    }

    /**
     * Fetch the full member view from the cluster-state process.
     *
     * @return array
     */
    public function getClusterView(): array
    {
        $ipc = $this->callProcessName(ClusterPlugin::PROCESS_NAME, ClusterState::class);
        if ($ipc === null) {
            return [];
        }
        $view = $ipc->getMemberView();
        return is_array($view) ? $view : [];
    }
}
