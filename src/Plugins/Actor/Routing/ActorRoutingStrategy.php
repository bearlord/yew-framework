<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Routing;

/**
 * Strategy that decides which actor worker process should host a new actor.
 */
interface ActorRoutingStrategy
{
    /**
     * Pick a process index for a new actor.
     *
     * @param int         $processCount Total number of actor worker processes
     * @param string|null $routingKey   Optional key for key-based routing (consistent hash)
     * @return int Selected process index in [0, processCount)
     */
    public function select(int $processCount, ?string $routingKey = null): int;
}
