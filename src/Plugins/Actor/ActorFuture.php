<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Coroutine\Channel\ChannelImpl;
use Yew\Plugins\Ipc\IpcException;

/**
 * A deferred result of an asynchronous actor "ask" call.
 *
 * Backed by a coroutine Channel, it follows the Promise pattern:
 *  - the producer (IPC layer) resolves it via {@see resolve()} / {@see reject()}
 *  - the consumer awaits it via {@see await()} or composes with {@see then()}
 *
 * Within a Swoole coroutine, await() suspends the current coroutine until the
 * actor process replies, then resumes with the value (or throws on error/timeout).
 */
class ActorFuture
{
    /**
     * @var ChannelImpl
     */
    private ChannelImpl $channel;

    /**
     * @var bool Whether the future has been settled
     */
    private bool $settled = false;

    /**
     * @var mixed Resolved value (valid only when settled and not failed)
     */
    private $value;

    /**
     * @var \Throwable|null Rejection reason
     */
    private ?\Throwable $error = null;

    /**
     * Create a future backed by a single-slot coroutine channel.
     */
    public function __construct()
    {
        $this->channel = new ChannelImpl(1);
    }

    /**
     * Resolve the future with a successful value.
     *
     * @param mixed $value
     */
    public function resolve($value): void
    {
        if ($this->settled) {
            return;
        }
        $this->settled = true;
        $this->value = $value;
        $this->channel->push(['ok' => true, 'value' => $value]);
    }

    /**
     * Reject the future with an error.
     *
     * @param \Throwable $error
     */
    public function reject(\Throwable $error): void
    {
        if ($this->settled) {
            return;
        }
        $this->settled = true;
        $this->error = $error;
        $this->channel->push(['ok' => false, 'error' => $error]);
    }

    /**
     * Block (suspending the current coroutine) until the result is available.
     *
     * @param float $timeout Seconds to wait; 0 means use the proxy's default
     * @return mixed
     * @throws \Throwable When the ask call failed or timed out
     */
    public function await(float $timeout = 0)
    {
        $result = $this->channel->pop($timeout > 0 ? $timeout : -1);

        if ($result === false) {
            throw new IpcException('Actor ask timed out');
        }

        if ($result['ok'] === true) {
            return $result['value'];
        }

        throw $result['error'];
    }

    /**
     * Whether the future has been resolved or rejected.
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->settled;
    }

    /**
     * Compose an async continuation.
     *
     * The callback receives the resolved value and its return value becomes the
     * (synchronously awaited) result of then(). Errors propagate via await().
     *
     * @param callable $onFulfilled
     * @return mixed
     * @throws \Throwable
     */
    public function then(callable $onFulfilled)
    {
        return $onFulfilled($this->await());
    }
}
