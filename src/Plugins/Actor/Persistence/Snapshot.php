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

    /**
     * Create a snapshot of an actor's state.
     *
     * @param string $actorName Owning actor name
     * @param mixed $state Captured actor state
     * @param int $lastSequence Sequence of the last event included in the state
     * @param float $timestamp Snapshot time (seconds)
     */
    public function __construct(string $actorName, $state, int $lastSequence, float $timestamp)
    {
        $this->actorName = $actorName;
        $this->state = $state;
        $this->lastSequence = $lastSequence;
        $this->timestamp = $timestamp;
    }

    /**
     * Owning actor name.
     *
     * @return string
     */
    public function getActorName(): string
    {
        return $this->actorName;
    }

    /**
     * Captured actor state.
     *
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Sequence of the last event included in this snapshot.
     *
     * @return int
     */
    public function getLastSequence(): int
    {
        return $this->lastSequence;
    }

    /**
     * Snapshot timestamp (seconds).
     *
     * @return float
     */
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
