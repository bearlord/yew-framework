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

    /**
     * Create an immutable actor event.
     *
     * @param string $actorName Owning actor name
     * @param string $type Event type / discriminator
     * @param mixed $payload Event payload
     * @param float $timestamp Event time (seconds)
     * @param int $sequence Monotonic sequence number within the actor
     */
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
     * Event type / discriminator.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Event payload.
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Event timestamp (seconds).
     *
     * @return float
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Monotonic sequence number.
     *
     * @return int
     */
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
