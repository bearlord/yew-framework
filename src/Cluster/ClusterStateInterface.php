<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

/**
 * Contract for cluster membership providers.
 *
 * Both the in-process shared-memory {@see ClusterState} and the cross-machine
 * UDP gossip {@see GossipClusterState} implement this interface, so routers and
 * supervision logic can depend on the contract rather than a concrete build.
 */
interface ClusterStateInterface
{
    /**
     * Register a listener invoked with the list of changed node ids after each tick.
     */
    public function registerListener(callable $cb): void;

    /**
     * @return ClusterMember[] Only nodes considered reachable (UP).
     */
    public function aliveNodes(): array;

    public function getNode(string $nodeId): ?ClusterMember;

    public function isLocal(string $nodeId): bool;
}
