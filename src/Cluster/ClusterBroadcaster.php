<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

/**
 * Fan-out contract for publishing a message to every node in the cluster.
 *
 * The Multicast module depends only on this interface, not on any concrete
 * cluster implementation, so it can broadcast across nodes without coupling
 * to GossipClusterState internals.
 */
interface ClusterBroadcaster
{
    /**
     * Broadcast a payload to every *other* alive node in the cluster.
     *
     * The local node is expected to handle the message itself (it already
     * has it), so implementations MUST exclude the local node from delivery.
     *
     * @param string $channel Logical channel name
     * @param string $message Opaque payload to deliver
     * @return void
     */
    public function broadcast(string $channel, string $message): void;
}
