<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Supervision;

use Yew\Plugins\Actor\Exception\ActorException;

/**
 * Restart the actor on failure, up to $maxRetries consecutive attempts.
 *
 */
class RestartStrategy implements SupervisorStrategy
{
    private int $maxRetries;

    /**
     * Build a restart strategy.
     *
     * @param int $maxRetries Maximum consecutive restarts before escalation
     */
    public function __construct(int $maxRetries = 3)
    {
        $this->maxRetries = $maxRetries;
    }

    /**
     * Restart on failure, escalate after $maxRetries consecutive attempts.
     *
     * @param \Throwable $throwable The failure that occurred
     * @param string $actorName Name of the failed actor
     * @param int $attempt Consecutive failure count
     * @return Directive RESTART or ESCALATE
     */
    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive
    {
        if ($attempt > $this->maxRetries) {
            return Directive::escalate();
        }

        return Directive::restart();
    }
}
