<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Persistence;

/**
 * An immutable fact that occurred to an actor.
 *
 * In event sourcing, the actor's state is the left-fold of all its events.
 * Persisting events (rather than snapshots) makes state reconstruction
 * deterministic and auditable.
 */
final class ActorEvent
{
    private string $actorName;
    private string $type;
    private $payload;
    private float $timestamp;
    private int $sequence;

    public function __construct(
        string $actorName,
        string $type,
        $payload,
        float $timestamp,
        int $sequence
    ) {
        $this->actorName = $actorName;
        $this->type = $type;
        $this->payload = $payload;
        $this->timestamp = $timestamp;
        $this->sequence = $sequence;
    }

    public function getActorName(): string
    {
        return $this->actorName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actorName' => $this->actorName,
            'type' => $this->type,
            'payload' => $this->payload,
            'timestamp' => $this->timestamp,
            'sequence' => $this->sequence,
        ];
    }
}
