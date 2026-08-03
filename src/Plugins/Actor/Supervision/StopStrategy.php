<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Supervision;

/**
 * Stop the actor permanently on the first failure.
 *
 * Safest default for actors that cannot safely continue after an error.
 */
class StopStrategy implements SupervisorStrategy
{
    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive
    {
        return Directive::stop();
    }
}
