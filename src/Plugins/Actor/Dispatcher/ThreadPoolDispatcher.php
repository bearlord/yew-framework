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
 * Thread-pool dispatcher for CPU-bound actors (Akka thread-pool / fork-join idea).
 *
 * PHP objects cannot be shared across OS threads, so the thread pool executes
 * ONLY side-effect-free, serialisable computations via {@see scheduleCpuBound()}.
 * Message handling itself (onHandleMessage) still runs inside the actor's own
 * coroutine to keep state coherent; the CPU-heavy body can be delegated:
 *
 *   $result = $this->getDispatcher()->scheduleCpuBound(
 *       fn($payload) => heavy_compute($payload),
 *       $message->getData()
 *   );
 *
 * When Swoole thread support is compiled in (Swoole\Thread::isSupported()),
 * computation runs on a real OS thread. Otherwise it gracefully degrades to a
 * coroutine and logs a warning — no behaviour break, just no true parallelism.
 */
class ThreadPoolDispatcher implements Dispatcher
{
    /**
     * @var int Pool size for real threads (ignored when unsupported)
     */
    private int $poolSize;

    public function __construct(int $poolSize = 4)
    {
        $this->poolSize = $poolSize;
    }

    public function dispatch(Actor $actor, ActorMessage $message): void
    {
        // Message handling stays in the actor's mailbox coroutine to preserve
        // Actor state integrity. Only the heavy compute payload is offloaded.
        $actor->onHandleMessage($message);
    }

    public function scheduleCpuBound(callable $task, $input)
    {
        if (class_exists(\Swoole\Thread::class) && \Swoole\Thread::isSupported()) {
            return $this->runOnThread($task, $input);
        }

        // Degraded mode: cooperative coroutine. Warn once.
        static $warned = false;
        if (!$warned) {
            $warned = true;
            trigger_error(
                "ThreadPoolDispatcher: Swoole\\Thread not supported, "
                . "falling back to coroutine execution (no true parallelism).",
                E_USER_WARNING
            );
        }

        $result = null;
        goWithContext(function () use ($task, $input, &$result) {
            $result = $task($input);
        });

        return $result;
    }

    /**
     * Execute the pure computation on a real Swoole thread.
     *
     * @param callable $task
     * @param mixed    $input
     * @return mixed
     */
    private function runOnThread(callable $task, $input)
    {
        // Serialise the closure + input, run off the event loop, read the result.
        // Swoole\Thread shares only serialisable data, so we pass a payload array.
        $payload = [
            'serialized_task' => serialize($task),
            'input' => $input,
        ];

        $thread = new \Swoole\Thread($payload);
        $thread->join();

        return $thread->returnValue ?? null;
    }
}
