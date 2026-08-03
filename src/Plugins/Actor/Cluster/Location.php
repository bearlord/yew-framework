<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

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
    public function __construct(
        private ClusterNode $node,
        private int $processId
    ) {
    }

    public function getNode(): ClusterNode
    {
        return $this->node;
    }

    public function getProcessId(): int
    {
        return $this->processId;
    }

    public function isLocal(): bool
    {
        return $this->node->isLocal();
    }

    public function __toString(): string
    {
        return sprintf('%s/process-%d', $this->node->getEndpoint(), $this->processId);
    }
}
