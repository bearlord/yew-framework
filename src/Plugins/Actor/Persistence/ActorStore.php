<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Persistence;

/**
 * Storage backend for actor persistence (event log + snapshots).
 *
 * Implementations are pluggable via the Strategy pattern:
 *  - FileActorStore:   local JSON files, survives process restart
 *  - (future) RedisActorStore / DbActorStore
 */
interface ActorStore
{
    /**
     * Append an event to the actor's event log.
     */
    public function appendEvent(ActorEvent $event): void;

    /**
     * Load all events for an actor, ordered by sequence.
     *
     * @return ActorEvent[]
     */
    public function loadEvents(string $actorName): array;

    /**
     * Save a snapshot of the actor's state.
     */
    public function saveSnapshot(Snapshot $snapshot): void;

    /**
     * Load the latest snapshot for an actor, or null if none.
     */
    public function loadSnapshot(string $actorName): ?Snapshot;

    /**
     * Delete all persisted state for an actor (events + snapshot).
     */
    public function delete(string $actorName): void;
}
