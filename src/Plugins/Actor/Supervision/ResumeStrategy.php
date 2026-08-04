<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Supervision;

/**
 * Resume the actor on failure: keep its state and continue with the next message.
 *
 */
class ResumeStrategy implements SupervisorStrategy
{
    /**
     * Resume the actor on failure, keeping its state and continuing.
     *
     * @param \Throwable $throwable The failure that occurred
     * @param string $actorName Name of the failed actor
     * @param int $attempt Consecutive failure count (unused)
     * @return Directive RESUME directive
     */
    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive
    {
        return Directive::resume();
    }
}
