<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Telemetry;

/**
 * Facade + global registry for actor telemetry (metrics + tracing).
 *
 * A single shared {@see ActorMetrics} instance collects per-actor counters.
 * Tracing is coroutine-local via {@see Tracer}. Everything is gated by
 * $enabled so it can be turned off in production with zero overhead.
 */
class ActorTelemetry
{
    private static ?ActorMetrics $metrics = null;
    private static bool $enabled = false;

    /**
     * Enable or disable telemetry collection.
     *
     * @param bool $enabled True to enable (lazy-creates the metrics table)
     */
    public static function enable(bool $enabled = true): void
    {
        self::$enabled = $enabled;
        if ($enabled && self::$metrics === null) {
            self::$metrics = new ActorMetrics();
        }
    }

    /**
     * Whether telemetry is currently enabled.
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * The shared metrics instance, or null when disabled.
     *
     * @return ActorMetrics|null
     */
    public static function metrics(): ?ActorMetrics
    {
        return self::$metrics;
    }

    /**
     * Record one processed message (latency + mailbox depth + error flag).
     */
    public static function record(string $actorName, float $seconds, int $mailboxDepth, bool $errored = false): void
    {
        if (!self::$enabled || self::$metrics === null) {
            return;
        }
        self::$metrics->recordProcess($actorName, $seconds, $mailboxDepth, $errored);
    }

    public static function snapshot(string $actorName): ?array
    {
        return self::$metrics?->snapshot($actorName);
    }

    /**
     * @return array<string, array>
     */
    public static function all(): array
    {
        return self::$metrics?->all() ?? [];
    }
}
