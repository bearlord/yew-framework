<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Dispatcher;

use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorMessage;

/**
 * Execution model abstraction (Akka-style Dispatcher).
 *
 * Decouples "how a mailbox message is executed" from the Actor itself so that
 * different execution strategies can be plugged in per actor:
 *  - coroutine : default Swoole single-thread coroutine model (event-loop bound)
 *  - pinned    : a dedicated, always-same coroutine for this actor (affinity)
 *  - thread-pool : CPU-bound work offloaded to real threads (when available)
 *
 * The dispatcher is handed a single already-popped message and decides in which
 * execution context {@see Actor::onHandleMessage()} runs.
 */
interface Dispatcher
{
    /**
     * Execute one message pulled from the actor's mailbox.
     *
     * @param Actor        $actor   The owning actor
     * @param ActorMessage $message The message to process
     */
    public function dispatch(Actor $actor, ActorMessage $message): void;

    /**
     * Offload a CPU-bound, side-effect-free callable to a real thread pool.
     *
     * Falls back to a coroutine when thread support is unavailable. The callable
     * MUST NOT capture the Actor or any shared object — only serialisable data.
     *
     * @param callable $task Pure computation: mixed $input -> mixed $result
     * @param mixed    $input
     * @return mixed Result of the computation
     */
    public function scheduleCpuBound(callable $task, $input);
}
