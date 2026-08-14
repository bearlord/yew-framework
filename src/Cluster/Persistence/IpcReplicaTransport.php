<?php

declare(strict_types=1);

namespace Yew\Cluster\Persistence;

use Yew\Cluster\GetClusterState;

/**
 * ReplicaTransport used in WORKER processes.
 *
 * The worker does not own the gossip engine (that lives solely in the
 * cluster-state process), so replication and replica lookups are proxied to
 * the cluster-state process over IPC via the GetClusterState trait. This keeps
 * every replica write on the single authority — no per-worker divergence.
 */
class IpcReplicaTransport implements ReplicaTransport
{
    use GetClusterState;

    public function replicateStoreEntry(string $actorName, string $kind, string $payload, int $ts): void
    {
        $this->clusterReplicate($actorName, $kind, $payload, $ts);
    }

    public function findReplica(string $actorName, string $kind): ?string
    {
        return $this->clusterFindReplica($actorName, $kind);
    }
}
