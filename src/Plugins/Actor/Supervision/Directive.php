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

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function resume(): self
    {
        return new self(self::RESUME);
    }

    public static function restart(): self
    {
        return new self(self::RESTART);
    }

    public static function stop(): self
    {
        return new self(self::STOP);
    }

    public static function escalate(): self
    {
        return new self(self::ESCALATE);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function is(string $value): bool
    {
        return $this->value === $value;
    }
}
