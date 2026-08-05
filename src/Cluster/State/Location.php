<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

/**
 * Physical location of an actor: which node + which worker process.
 *
 * This is the single source of truth for "where is actor X". It makes
 * addressing location-transparent: callers ask for a location, not for a
 * process object, so the same code works whether the actor lives locally or
 * on a remote cluster node.
 */
class Location
{
    /**
     * Create an actor location.
     *
     * @param ClusterNode $node Owning node
     * @param int $processId Worker process id hosting the actor
     * @param string|null $actorName Optional actor name (used by the remote proxy)
     */
    public function __construct(
        private ClusterNode $node,
        private int $processId,
        private ?string $actorName = null
    ) {
    }

    /**
     * Owning node.
     *
     * @return ClusterNode
     */
    public function getNode(): ClusterNode
    {
        return $this->node;
    }

    /**
     * Worker process id hosting the actor.
     *
     * @return int
     */
    public function getProcessId(): int
    {
        return $this->processId;
    }

    /**
     * Actor name carried on this location.
     *
     * @return string|null
     */
    public function getActorName(): ?string
    {
        return $this->actorName;
    }

    /**
     * Attach the actor name (called by the remote proxy).
     *
     * @param string|null $actorName
     */
    public function setActorName(?string $actorName): void
    {
        $this->actorName = $actorName;
    }

    /**
     * Whether the location is on the local node.
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->node->isLocal();
    }

    public function __toString(): string
    {
        return sprintf('%s/process-%d', $this->node->getEndpoint(), $this->processId);
    }
}
