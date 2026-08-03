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

    public function __construct(int $maxRetries = 3)
    {
        $this->maxRetries = $maxRetries;
    }

    public function decide(\Throwable $throwable, string $actorName, int $attempt): Directive
    {
        if ($attempt > $this->maxRetries) {
            return Directive::escalate();
        }

        return Directive::restart();
    }
}
