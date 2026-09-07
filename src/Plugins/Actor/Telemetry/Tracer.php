<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Telemetry;

use Swoole\Coroutine;

/**
 * Per-coroutine tracer that propagates the active trace id.
 *
 * The active trace id is stored in a coroutine-local context so that any IPC
 * call made while handling a message automatically carries the same trace id,
 * which the remote actor picks up to continue the span — giving an end-to-end
 * distributed trace across actor processes.
 */
class Tracer
{
    /**
     * @var array<int, string> Coroutine id => current trace id
     */
    private static array $activeTrace = [];

    /**
     * Start a new trace, return its root span, and bind it to this coroutine.
     */
    public static function start(string $name = 'actor.root'): Span
    {
        $span = new Span($name);
        self::bind($span->getTraceId());

        return $span;
    }

    /**
     * Continue an existing trace (received from a remote caller).
     */
    public static function continue(string $traceId, string $name = 'actor.span'): Span
    {
        $span = new Span($name, null, $traceId);
        self::bind($traceId);

        return $span;
    }

    private static function bind(string $traceId): void
    {
        $cid = Coroutine::getCid();
        self::$activeTrace[$cid] = $traceId;
    }

    /**
     * Current coroutine's trace id, or a fresh one if none is active.
     */
    public static function currentTraceId(): string
    {
        $cid = Coroutine::getCid();
        if (isset(self::$activeTrace[$cid])) {
            return self::$activeTrace[$cid];
        }
        $id = Span::generateId();
        self::$activeTrace[$cid] = $id;

        return $id;
    }

    public static function clear(): void
    {
        unset(self::$activeTrace[Coroutine::getCid()]);
    }
}
