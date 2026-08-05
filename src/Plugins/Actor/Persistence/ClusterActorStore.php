<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Persistence;

use Yew\Cluster\GossipClusterState;

/**
 * Cross-node durable ActorStore.
 *
 * Wraps a local FileActorStore for fast reads/writes and replicates every
 * mutation to a quorum of peer nodes through the gossip transport
 * (fire-and-forget; gossip cadence re-delivers any lost replica, identical to
 * the fragment retransmission design). Reads are served from the local copy
 * first, and only fall back to a replicated copy buffered from peers when the
 * owning node is down and the local copy is missing â€?enabling cross-node
 * actor resurrection (failover / migration).
 *
 * A single ClusterActorStore instance is injected into every actor on a node;
 * the owning actor binds its name via setActorName() before each access
 * (single-threaded model, same as FileActorStore).
 */
class ClusterActorStore implements ActorStore
{
    private string $actorName = '';

    private ?GossipClusterState $cluster = null;

    /**
     * Build a cluster-backed store wrapping a local FileActorStore.
     *
     * @param FileActorStore $local Local store for fast read/write
     * @param int $replicationFactor Number of peer replicas per mutation
     */
    public function __construct(
        private FileActorStore $local,
        private int $replicationFactor = 2
    ) {
    }

    /**
     * Bind the owning actor name before access (single-threaded model).
     *
     * @param string $actorName Actor name
     * @return self
     */
    public function setActorName(string $actorName): self
    {
        $this->actorName = $actorName;
        $this->local->setActorName($actorName);
        return $this;
    }

    /**
     * Attach the cluster state used for cross-node replication/lookup.
     *
     * @param GossipClusterState $cluster Cluster membership/transport
     */
    public function setCluster(GossipClusterState $cluster): void
    {
        $this->cluster = $cluster;
    }

    /**
     * Mirrors FileActorStore::init() so it can be chained from Actor::init().
     */
    public function init(): void
    {
        $this->local->init();
    }

    /**
     * Adjust how many peer nodes each mutation is replicated to.
     *
     * @param int $factor Desired replication factor (minimum 1)
     */
    public function setReplicationFactor(int $factor): void
    {
        $this->replicationFactor = max(1, $factor);
    }

    /**
     * Persist an event locally and replicate it to peers.
     *
     * @param ActorEvent $event Event to persist
     */
    public function appendEvent(ActorEvent $event): void
    {
        $this->local->appendEvent($event);
        // Replicate the full event list under the event's own actor name as a
        // JSON array (matching toRow() and what ingestReplica()/loadEvents()
        // expect). ClusterActorStore is a shared singleton, so we must NOT rely
        // on a stored $this->actorName that could be clobbered by a concurrent
        // actor's setActorName().
        $events = $this->local->loadEvents($event->getActorName());
        $rows = array_map(static fn(ActorEvent $e) => $e->toArray(), $events);
        $this->replicate($event->getActorName(), 'events', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Load events, falling back to a peer replica when the local copy is gone.
     *
     * @param string $actorName Actor name
     * @return ActorEvent[]
     */
    public function loadEvents(string $actorName): array
    {
        $events = $this->local->loadEvents($actorName);
        if (!empty($events) || $this->cluster === null) {
            return $events;
        }
        // Local copy gone (owning node failed): try a peer-replicated copy.
        $json = $this->cluster->findReplica($actorName, 'events');
        if ($json === null) {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ev = new ActorEvent(
                (string) $row['actorName'],
                (string) $row['type'],
                $row['payload'] ?? null,
                (float) ($row['timestamp'] ?? 0),
                (int) ($row['sequence'] ?? 0)
            );
            $out[] = $ev;
            $this->local->appendEvent($ev); // cache locally
        }
        return $out;
    }

    /**
     * Persist a snapshot locally and replicate it to peers.
     *
     * @param Snapshot $snapshot Snapshot to persist
     */
    public function saveSnapshot(Snapshot $snapshot): void
    {
        $this->local->saveSnapshot($snapshot);
        $this->replicate($snapshot->getActorName(), 'snapshots', json_encode($snapshot->toArray(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Load a snapshot, falling back to a peer replica when the local copy is gone.
     *
     * @param string $actorName Actor name
     * @return Snapshot|null
     */
    public function loadSnapshot(string $actorName): ?Snapshot
    {
        $snap = $this->local->loadSnapshot($actorName);
        if ($snap !== null || $this->cluster === null) {
            return $snap;
        }
        $json = $this->cluster->findReplica($actorName, 'snapshots');
        if ($json === null) {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        $snap = new Snapshot(
            (string) $decoded['actorName'],
            $decoded['state'] ?? null,
            (int) ($decoded['lastSequence'] ?? 0),
            (float) ($decoded['timestamp'] ?? 0)
        );
        $this->local->saveSnapshot($snap);
        return $snap;
    }

    /**
     * Delete an actor's persisted state locally and on peers.
     *
     * @param string $actorName Actor name
     */
    public function delete(string $actorName): void
    {
        $this->local->delete($actorName);
        $this->replicate($actorName, 'clear', '[]');
    }

    /**
     * Ingest a replica entry pushed by a peer (called by the cluster layer when
     * a STORE_PUT gossip message arrives).
     */
    public function ingestReplica(string $kind, string $actorName, string $payloadJson): void
    {
        if ($kind === 'events') {
            $decoded = json_decode($payloadJson, true);
            if (!is_array($decoded)) {
                return;
            }
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $this->local->appendEvent(new ActorEvent(
                    (string) $row['actorName'],
                    (string) $row['type'],
                    $row['payload'] ?? null,
                    (float) ($row['timestamp'] ?? 0),
                    (int) ($row['sequence'] ?? 0)
                ));
            }
        } elseif ($kind === 'snapshots') {
            $decoded = json_decode($payloadJson, true);
            if (!is_array($decoded)) {
                return;
            }
            $this->local->saveSnapshot(new Snapshot(
                (string) $decoded['actorName'],
                $decoded['state'] ?? null,
                (int) ($decoded['lastSequence'] ?? 0),
                (float) ($decoded['timestamp'] ?? 0)
            ));
        } elseif ($kind === 'clear') {
            $this->local->delete($actorName);
        }
    }

    /**
     * @return array{actorName:string,kind:string,payload:string}|null
     */
    public function exportForReplica(string $kind, string $actorName): ?array
    {
        if ($kind === 'events') {
            $events = $this->local->loadEvents($actorName);
            if (empty($events)) {
                return null;
            }
            $rows = array_map(static fn(ActorEvent $e) => $e->toArray(), $events);
            return ['actorName' => $actorName, 'kind' => 'events', 'payload' => json_encode($rows, JSON_UNESCAPED_UNICODE)];
        }
        if ($kind === 'snapshots') {
            $snap = $this->local->loadSnapshot($actorName);
            if ($snap === null) {
                return null;
            }
            return ['actorName' => $actorName, 'kind' => 'snapshots', 'payload' => json_encode($snap->toArray(), JSON_UNESCAPED_UNICODE)];
        }
        if ($kind === 'clear') {
            return ['actorName' => $actorName, 'kind' => 'clear', 'payload' => '[]'];
        }
        return null;
    }

    /**
     * Replicate a store entry to peer nodes via the gossip transport.
     *
     * @param string $actorName Actor name
     * @param string $kind Entry kind: events | snapshots | clear
     * @param string $payload JSON-encoded payload
     */
    private function replicate(string $actorName, string $kind, string $payload): void
    {
        if ($this->cluster !== null) {
            $this->cluster->replicateStoreEntry($actorName, $kind, $payload, time());
        }
    }
}
