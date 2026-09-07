<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Supervision;

/**
 * Always escalate the failure to the caller / higher-level supervisor.
 */
class EscalateStrategy implements SupervisorStrategy
{
    /**
     * Always escalate the failure to the caller / higher-level supervisor.
     *
     * @param \Throwable $throwable The failure that occurred
     * @param string $actorName Name of the failed actor
     * @param int $attempt Consecutive failure count (unused)
     * @return Directive ESCALATE directive
     */
    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive
    {
        return Directive::escalate();
    }
}
