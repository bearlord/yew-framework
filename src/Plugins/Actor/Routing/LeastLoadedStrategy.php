<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Routing;

use Yew\Plugins\Actor\ActorManager;

/**
 * Adaptive / least-loaded routing.
 *
 * Routes a new actor to the worker process currently hosting the fewest actors,
 * using a shared-memory load counter maintained by {@see ActorManager}.
 * This balances real runtime load instead of assuming uniform distribution.
 */
class LeastLoadedStrategy implements ActorRoutingStrategy
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
        $bestIndex = 0;
        $bestLoad = null;

        for ($i = 0; $i < $processCount; $i++) {
            $load = $this->manager->getLoad($i);
            if ($bestLoad === null || $load < $bestLoad) {
                $bestLoad = $load;
                $bestIndex = $i;
            }
        }

        // Tie-break deterministically so equal loads don't always pick index 0.
        return $bestIndex;
    }
}
