<?php

declare(strict_types=1);

namespace Yew\Cluster\Persistence;

/**
 * Transport abstraction for cross-node ActorStore replication.
 *
 * Decouples ClusterActorStore from the concrete GossipClusterState engine so
 * the store can run in a worker (proxying to the cluster-state process over
 * IPC) or inside the cluster-state process (delegating to the mounted engine)
 * without knowing which side it lives on.
 */
interface ReplicaTransport
{
    /**
     * Replicate a store mutation to peer nodes.
     *
     * @param string $actorName Actor name
     * @param string $kind      Entry kind: events | snapshots | meta | clear
     * @param string $payload   JSON-encoded payload
     * @param int    $ts        Replica timestamp (epoch seconds)
     */
    public function replicateStoreEntry(string $actorName, string $kind, string $payload, int $ts): void;

    /**
     * Look up a replicated copy of a store entry (used for failover reads when
     * the local copy is gone because the owning node died).
     *
     * @param string $actorName Actor name
     * @param string $kind      Entry kind: events | snapshots | meta
     * @return string|null JSON payload, or null if no replica is buffered
     */
    public function findReplica(string $actorName, string $kind): ?string;
}
