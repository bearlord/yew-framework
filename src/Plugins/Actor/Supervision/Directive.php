<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Supervision;

/**
 * Directives a supervisor may apply after an actor failure.
 *
 * Mirrors the classic Actor-model supervision directives:
 *  - RESUME:  keep the actor and its state, continue processing the next message
 *  - RESTART: recreate the actor, preserving identity but rebuilding volatile state
 *  - STOP:    terminate the actor permanently
 *  - ESCALATE: rethrow the failure to the caller / higher-level supervisor
 */
final class Directive
{
    public const RESUME = 'resume';
    public const RESTART = 'restart';
    public const STOP = 'stop';
    public const ESCALATE = 'escalate';

    private string $value;

    /**
     * Private constructor; use the named factory methods below.
     *
     * @param string $value One of the Directive constants
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Resume the actor, keeping its state.
     *
     * @return self
     */
    public static function resume(): self
    {
        return new self(self::RESUME);
    }

    /**
     * Restart the actor, rebuilding its volatile state.
     *
     * @return self
     */
    public static function restart(): self
    {
        return new self(self::RESTART);
    }

    /**
     * Stop the actor permanently.
     *
     * @return self
     */
    public static function stop(): self
    {
        return new self(self::STOP);
    }

    /**
     * Escalate the failure to a higher-level supervisor.
     *
     * @return self
     */
    public static function escalate(): self
    {
        return new self(self::ESCALATE);
    }

    /**
     * Get the underlying directive value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Compare this directive against a value.
     *
     * @param string $value Value to compare with
     * @return bool True when equal
     */
    public function is(string $value): bool
    {
        return $this->value === $value;
    }
}
