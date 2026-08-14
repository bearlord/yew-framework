<?php

declare(strict_types=1);

namespace Yew\Cluster\Persistence;

use Yew\Cluster\State\ClusterState;

/**
 * ReplicaTransport used INSIDE the cluster-state process.
 *
 * Forwards replication/lookup to the mounted GossipClusterState engine via the
 * ClusterState facade (which already holds the engine as its single authority).
 * This is the only side that actually owns the gossip replica buffer.
 */
class LocalReplicaTransport implements ReplicaTransport
{
    public function __construct(private ClusterState $state)
    {
    }

    public function replicateStoreEntry(string $actorName, string $kind, string $payload, int $ts): void
    {
        $this->state->replicateStoreEntry($actorName, $kind, $payload, $ts);
    }

    public function findReplica(string $actorName, string $kind): ?string
    {
        return $this->state->findReplica($actorName, $kind);
    }
}
