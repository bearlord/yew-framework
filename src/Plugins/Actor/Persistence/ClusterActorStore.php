<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Persistence;

use Swoole\Coroutine\Mutex;
use Swoole\Timer;
use Yew\Cluster\Persistence\ReplicaTransport;
use Yew\Coroutine\Server\Server;

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

    private ?ReplicaTransport $cluster = null;

    /**
     * Async replication queue. Mutations are enqueued here instead of being
     * replicated synchronously (which blocks the calling actor coroutine on an
     * IPC round-trip to the cluster-state process). A background timer drains
     * the queue in batches. This trades strict "replicated before return"
     * durability for much higher actor throughput under concurrency.
     *
     * @var array<int,array{actorName:string,kind:string,payload:string}>
     */
    private array $pendingReplicas = [];

    /**
     * Guards $pendingReplicas (the actor process may call persist() from many
     * concurrent actor coroutines).
     */
    private Mutex $replicaMutex;

    /**
     * Ensures the background flush timer is started exactly once per process.
     */
    private bool $flushTimerStarted = false;

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
        $this->replicaMutex = new Mutex();
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
     * Attach the replica transport used for cross-node replication/lookup.
     *
     * @param ReplicaTransport $cluster Replica transport (local or IPC-backed)
     */
    public function setCluster(ReplicaTransport $cluster): void
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
        // Hot path: O(1) append instead of the old full-rewrite loadEvents +
        // writeJson, so per-increment cost no longer grows with event count.
        $this->local->appendEventLine($event);
        // Replicate the full event list under the event's own actor name as a
        // JSON array (matching toRow() and what ingestReplica()/loadEvents()
        // expect). The replication is enqueued and drained by a background
        // timer (fire-and-forget), so this O(N) read does not block the actor.
        $events = $this->local->loadEvents($event->getActorName());
        $rows = array_map(static fn(ActorEvent $e) => $e->toArray(), $events);
        $this->enqueueReplica($event->getActorName(), 'events', json_encode($rows, JSON_UNESCAPED_UNICODE));
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
        $this->enqueueReplica($snapshot->getActorName(), 'snapshots', json_encode($snapshot->toArray(), JSON_UNESCAPED_UNICODE));
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
        $this->enqueueReplica($actorName, 'clear', '[]');
    }

    /**
     * Persist the actor's class name locally and replicate it to peers so a
     * failover node can recreate the actor without an external class mapping.
     */
    public function saveMeta(string $actorName, string $class): void
    {
        $this->local->saveMeta($actorName, $class);
        $this->enqueueReplica($actorName, 'meta', json_encode([
            'actorName' => $actorName,
            'class' => $class,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Resolve the actor's class name for failover.
     *
     * Prefers the locally persisted meta, then falls back to any replica that
     * holds the meta entry, so a freshly promoted node can still rebuild it.
     */
    public function loadClass(string $actorName): ?string
    {
        $local = $this->local->loadClass($actorName);
        if ($local !== null) {
            return $local;
        }
        if ($this->cluster === null) {
            return null;
        }
        $json = $this->cluster->findReplica($actorName, 'meta');
        if ($json === null) {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) && !empty($decoded['class']) ? $decoded['class'] : null;
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
        } elseif ($kind === 'meta') {
            $decoded = json_decode($payloadJson, true);
            if (is_array($decoded) && !empty($decoded['class'])) {
                $this->local->saveMeta((string) $decoded['actorName'], (string) $decoded['class']);
            }
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
        if ($kind === 'meta') {
            $class = $this->local->loadClass($actorName);
            if ($class === null) {
                return null;
            }
            return ['actorName' => $actorName, 'kind' => 'meta', 'payload' => json_encode([
                'actorName' => $actorName,
                'class' => $class,
            ], JSON_UNESCAPED_UNICODE)];
        }
        if ($kind === 'clear') {
            return ['actorName' => $actorName, 'kind' => 'clear', 'payload' => '[]'];
        }
        return null;
    }

    /**
     * Enqueue a replication request instead of performing it synchronously.
     *
     * The calling actor coroutine returns immediately; a background timer
     * (see startFlushTimer / flushReplicas) drains the queue in batches.
     * This removes the IPC round-trip to the cluster-state process from the
     * actor's request-critical path, eliminating the serialization/timeout
     * bottleneck under concurrency.
     *
     * Durability note: replicas become visible to peers on the next flush
     * (best-effort, low latency). If the process crashes before a queued
     * entry is flushed, that last batch may be lost ¡ª acceptable for
     * eventually-consistent counter-style state.
     *
     * @param string $actorName Actor name
     * @param string $kind Entry kind: events | snapshots | clear | meta
     * @param string $payload JSON-encoded payload
     */
    private function enqueueReplica(string $actorName, string $kind, string $payload): void
    {
        if ($this->cluster === null) {
            Server::$instance->getLog()->warning(
                "ClusterActorStore: enqueueReplica($actorName/$kind) dropped ¡ª no ReplicaTransport wired"
            );
            return;
        }
        $this->replicaMutex->lock();
        $this->pendingReplicas[] = [
            'actorName' => $actorName,
            'kind' => $kind,
            'payload' => $payload,
        ];
        $this->replicaMutex->unlock();
    }

    /**
     * Drain and send all queued replication entries. Safe to call repeatedly
     * (e.g. from a timer tick); never throws into the caller.
     */
    public function flushReplicas(): void
    {
        if ($this->cluster === null || empty($this->pendingReplicas)) {
            return;
        }
        $this->replicaMutex->lock();
        $batch = $this->pendingReplicas;
        $this->pendingReplicas = [];
        $this->replicaMutex->unlock();

        foreach ($batch as $item) {
            try {
                $this->replicate($item['actorName'], $item['kind'], $item['payload']);
            } catch (\Throwable $e) {
                // Re-queue on failure so the replica is not silently lost; the
                // next tick will retry it.
                $this->replicaMutex->lock();
                $this->pendingReplicas[] = $item;
                $this->replicaMutex->unlock();
                Server::$instance->getLog()->warning(sprintf(
                    'ClusterActorStore: flushReplicas(%s/%s) failed, requeued: %s',
                    $item['actorName'],
                    $item['kind'],
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Start the background replication flush timer exactly once per process.
     *
     * Called from ActorProcess after the store is wired into DI. The timer
     * runs inside the actor process and periodically drains pendingReplicas.
     *
     * @param int $intervalMs Flush interval in milliseconds (default 200ms)
     */
    public function startFlushTimer(int $intervalMs = 200): void
    {
        if ($this->flushTimerStarted) {
            return;
        }
        $this->flushTimerStarted = true;
        Timer::tick($intervalMs, function (): void {
            $this->flushReplicas();
        });
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
        } else {
            // Should not happen: both the worker and cluster-state processes wire
            // a ReplicaTransport at startup. A null here means the store was
            // constructed without setCluster() ¡ª surface it instead of silently
            // dropping the replica.
            Server::$instance->getLog()->warning(
                "ClusterActorStore: replicate($actorName/$kind) dropped ¡ª no ReplicaTransport wired"
            );
        }
    }
}
