<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Persistence;

/**
 * File-based ActorStore. Persists the event log and snapshots as JSON files
 * under a configurable directory. Survives process restart (unlike memory).
 *
 * Layout:
 *   {dir}/{actorName}.events.json   -> list of event arrays
 *   {dir}/{actorName}.snapshot.json -> latest snapshot array
 */
class FileActorStore implements ActorStore
{
    private string $dir;

    /**
     * Create the store and ensure the storage directory exists.
     *
     * @param string $dir Root directory for JSON persistence files
     */
    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/\\');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    /**
     * Bind the owning actor name before use. Provided for parity with
     * ClusterActorStore; FileActorStore addresses actors via explicit method
     * arguments, so this is a no-op beyond returning $this.
     *
     * @param string $actorName Actor name (unused here)
     * @return self
     */
    public function setActorName(string $actorName): self
    {
        return $this;
    }

    /**
     * Optional initialization hook (parity with ClusterActorStore).
     */
    public function init(): void
    {
    }

    /**
     * Path of the events JSON file for an actor.
     *
     * @param string $actorName Actor name
     * @return string
     */
    private function eventsFile(string $actorName): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $this->sanitize($actorName) . '.events.json';
    }

    /**
     * Path of the snapshot JSON file for an actor.
     *
     * @param string $actorName Actor name
     * @return string
     */
    private function snapshotFile(string $actorName): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $this->sanitize($actorName) . '.snapshot.json';
    }

    /**
     * Make an actor name safe to use as a filename.
     *
     * @param string $actorName Actor name
     * @return string Sanitized name
     */
    private function sanitize(string $actorName): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $actorName);
    }

    /**
     * Read and decode a JSON file; returns [] on missing/corrupt file.
     *
     * @param string $file Absolute file path
     * @return array
     */
    private function readJson(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode and write an array to a JSON file.
     *
     * @param string $file Absolute file path
     * @param array $data Data to persist
     */
    private function writeJson(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Append an event to the actor's event log.
     *
     * @param ActorEvent $event Event to append
     */
    public function appendEvent(ActorEvent $event): void
    {
        $events = $this->readJson($this->eventsFile($event->getActorName()));
        $events[] = $event->toArray();
        $this->writeJson($this->eventsFile($event->getActorName()), $events);
    }

    /**
     * Load and reconstruct all events for an actor, in sequence order.
     *
     * @param string $actorName Actor name
     * @return ActorEvent[]
     */
    public function loadEvents(string $actorName): array
    {
        $rows = $this->readJson($this->eventsFile($actorName));
        $events = [];
        foreach ($rows as $row) {
            $events[] = new ActorEvent(
                $row['actorName'],
                $row['type'],
                $row['payload'],
                (float) $row['timestamp'],
                (int) $row['sequence']
            );
        }

        return $events;
    }

    /**
     * Save a snapshot for an actor.
     *
     * @param Snapshot $snapshot Snapshot to persist
     */
    public function saveSnapshot(Snapshot $snapshot): void
    {
        $this->writeJson($this->snapshotFile($snapshot->getActorName()), $snapshot->toArray());
    }

    /**
     * Load the latest snapshot for an actor, or null if none.
     *
     * @param string $actorName Actor name
     * @return Snapshot|null
     */
    public function loadSnapshot(string $actorName): ?Snapshot
    {
        $rows = $this->readJson($this->snapshotFile($actorName));
        if (empty($rows)) {
            return null;
        }

        return new Snapshot(
            $rows['actorName'],
            $rows['state'],
            (int) $rows['lastSequence'],
            (float) $rows['timestamp']
        );
    }

    /**
     * Delete all persisted state for an actor (events + snapshot).
     *
     * @param string $actorName Actor name
     */
    public function delete(string $actorName): void
    {
        @unlink($this->eventsFile($actorName));
        @unlink($this->snapshotFile($actorName));
    }
}
