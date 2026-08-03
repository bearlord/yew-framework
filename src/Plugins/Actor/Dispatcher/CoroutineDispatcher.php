<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Dispatcher;

use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorMessage;
use Yew\Coroutine\Server\GoWithContext as goWithContext;

/**
 * Default dispatcher: Swoole single-thread coroutine model.
 *
 * The message is processed in the actor's mailbox coroutine (the same coroutine
 * that pops the mailbox), so execution is cooperatively scheduled by the event
 * loop. This is the baseline behaviour the framework had before this abstraction.
 */
class CoroutineDispatcher implements Dispatcher
{
    public function dispatch(Actor $actor, ActorMessage $message): void
    {
        $actor->onHandleMessage($message);
    }

    public function scheduleCpuBound(callable $task, $input)
    {
        // No thread pool: run cooperatively in a fresh coroutine.
        $result = null;
        goWithContext(function () use ($task, $input, &$result) {
            $result = $task($input);
        });

        return $result;
    }
}
