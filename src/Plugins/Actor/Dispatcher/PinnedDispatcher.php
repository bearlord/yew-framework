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
 * Pinned dispatcher (Akka PinnedDispatcher equivalent).
 *
 * The actor owns a dedicated, private channel and a single long-lived coroutine
 * that is the ONLY execution context for its messages. This guarantees:
 *  - strict in-order, single-flight message processing (no interleaving)
 *  - CPU/affinity locality: the actor never migrates between coroutines
 *
 * Messages are pushed onto the private channel from {@see dispatch()} and
 * consumed by the pinned coroutine, which calls onHandleMessage().
 */
class PinnedDispatcher implements Dispatcher
{
    /**
     * @var \Swoole\Coroutine\Channel Private, per-actor mailbox
     */
    private $pinnedChannel;

    /**
     * @var Actor|null Owning actor (set on first dispatch)
     */
    private ?Actor $actor = null;

    /**
     * @var bool Whether the pinned loop is running
     */
    private bool $running = false;

    public function dispatch(Actor $actor, ActorMessage $message): void
    {
        if ($this->actor === null) {
            $this->actor = $actor;
            $this->pinnedChannel = new \Swoole\Coroutine\Channel(1024);
            $this->startPinnedLoop();
        }

        $this->pinnedChannel->push($message);
    }

    /**
     * Run the dedicated coroutine that serialises all message handling.
     */
    private function startPinnedLoop(): void
    {
        if ($this->running) {
            return;
        }
        $this->running = true;

        $actor = $this->actor;
        $channel = $this->pinnedChannel;

        goWithContext(function () use ($actor, $channel) {
            while (true) {
                $message = $channel->pop();
                if ($message === false) {
                    break;
                }
                // Executed strictly in-order inside this single coroutine.
                $actor->onHandleMessage($message);
            }
        });
    }

    public function scheduleCpuBound(callable $task, $input)
    {
        // Pinned actors still have no thread pool; run in the event loop.
        $result = null;
        goWithContext(function () use ($task, $input, &$result) {
            $result = $task($input);
        });

        return $result;
    }

    /**
     * Stop the pinned loop (called on actor termination).
     */
    public function shutdown(): void
    {
        if ($this->pinnedChannel !== null) {
            $this->pinnedChannel->close();
        }
        $this->running = false;
    }
}
