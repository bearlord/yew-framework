<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Log;

/**
 * Log severity levels for the Actor logger.
 *
 * Mirrors the standard PSR-3 style ordering so messages can be filtered and
 * rendered with a human-readable tag.
 */
final class Level
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /**
     * Upper-cased, bracketed tag for display, e.g. "[ERROR]".
     */
    public static function tag(string $level): string
    {
        return '[' . strtoupper($level) . ']';
    }

    /**
     * Whether $level is at least as severe as $minimum (used for filtering).
     */
    public static function satisfies(string $level, string $minimum): bool
    {
        $order = [self::DEBUG => 0, self::INFO => 1, self::WARNING => 2, self::ERROR => 3];
        return ($order[$level] ?? 1) >= ($order[$minimum] ?? 1);
    }
}
