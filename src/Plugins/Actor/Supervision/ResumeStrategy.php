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
    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive
    {
        return Directive::resume();
    }
}
