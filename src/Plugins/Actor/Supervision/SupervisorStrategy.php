<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Supervision;

/**
 * Decides what to do when an actor throws while handling a message.
 *
 */
interface SupervisorStrategy
{
    /**
     * Decide the directive for a given failure.
     *
     * @param \Throwable $throwable The exception thrown by the actor
     * @param string     $actorName The failing actor's name
     * @param int        $attempt   Consecutive failure count for this actor
     * @return Directive
     */
    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive;
}
