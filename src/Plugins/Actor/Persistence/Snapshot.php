<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Persistence;

/**
 * A point-in-time snapshot of an actor's state.
 *
 * Snapshots avoid replaying the entire event log on recovery: replay starts
 * from the latest snapshot, then only the events after it.
 */
final class Snapshot
{
    private string $actorName;
    private $state;
    private int $lastSequence;
    private float $timestamp;

    public function __construct(string $actorName, $state, int $lastSequence, float $timestamp)
    {
        $this->actorName = $actorName;
        $this->state = $state;
        $this->lastSequence = $lastSequence;
        $this->timestamp = $timestamp;
    }

    public function getActorName(): string
    {
        return $this->actorName;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getLastSequence(): int
    {
        return $this->lastSequence;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actorName' => $this->actorName,
            'state' => $this->state,
            'lastSequence' => $this->lastSequence,
            'timestamp' => $this->timestamp,
        ];
    }
}
