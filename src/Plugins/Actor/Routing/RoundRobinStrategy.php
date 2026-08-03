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

    public function __construct(ActorManager $manager)
    {
        $this->manager = $manager;
    }

    public function select(int $processCount, ?string $routingKey = null): int
    {
        $counter = $this->manager->getAtomic()->add();

        return $counter % $processCount;
    }
}
