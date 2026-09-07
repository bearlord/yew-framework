<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Routing;

use Yew\Plugins\Actor\ActorManager;

/**
 * Classic round-robin routing backed by a shared atomic counter.
 *
 * Evenly spreads actors across worker processes regardless of their key.
 */
class RoundRobinStrategy implements ActorRoutingStrategy
{
    /**
     * @var ActorManager
     */
    private ActorManager $manager;

    /**
     * Build a round-robin strategy.
     *
     * @param ActorManager $manager Source of the shared atomic counter
     */
    public function __construct(ActorManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Pick the next worker in round-robin order.
     *
     * @param int $processCount Total number of worker processes
     * @param string|null $routingKey Unused by this strategy
     * @return int Selected worker index
     */
    public function select(int $processCount, ?string $routingKey = null): int
    {
        $counter = $this->manager->getAtomic()->add();

        return $counter % $processCount;
    }
}
