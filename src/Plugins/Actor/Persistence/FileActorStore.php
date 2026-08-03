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

    private function eventsFile(string $actorName): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $this->sanitize($actorName) . '.events.json';
    }

    private function snapshotFile(string $actorName): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $this->sanitize($actorName) . '.snapshot.json';
    }

    private function sanitize(string $actorName): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $actorName);
    }

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

    private function writeJson(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function appendEvent(ActorEvent $event): void
    {
        $events = $this->readJson($this->eventsFile($event->getActorName()));
        $events[] = $event->toArray();
        $this->writeJson($this->eventsFile($event->getActorName()), $events);
    }

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

    public function saveSnapshot(Snapshot $snapshot): void
    {
        $this->writeJson($this->snapshotFile($snapshot->getActorName()), $snapshot->toArray());
    }

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

    public function delete(string $actorName): void
    {
        @unlink($this->eventsFile($actorName));
        @unlink($this->snapshotFile($actorName));
    }
}
