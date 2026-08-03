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
    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive
    {
        return Directive::escalate();
    }
}
