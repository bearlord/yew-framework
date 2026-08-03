<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Telemetry;

/**
 * OpenTelemetry-style span.
 *
 * Captures a unit of work: a trace (correlated across processes), a span id,
 * an optional parent span (for nesting / cross-process linkage), a name and
 * start/end timestamps. Serialisable so it can travel inside an IPC message
 * to continue the same trace on a remote actor.
 */
class Span
{
    private string $traceId;
    private string $spanId;
    private ?string $parentSpanId;
    private string $name;
    private float $startTime;
    private ?float $endTime = null;

    public function __construct(string $name, ?string $parentSpanId = null, ?string $traceId = null)
    {
        $this->traceId = $traceId ?? self::generateId();
        $this->spanId = self::generateId();
        $this->parentSpanId = $parentSpanId;
        $this->name = $name;
        $this->startTime = microtime(true);
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function end(): void
    {
        $this->endTime = microtime(true);
    }

    public function durationSeconds(): ?float
    {
        return $this->endTime === null ? null : ($this->endTime - $this->startTime);
    }

    public function startTime(): float
    {
        return $this->startTime;
    }

    /**
     * Serialise for IPC transport.
     */
    public function toArray(): array
    {
        return [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'parentSpanId' => $this->parentSpanId,
            'name' => $this->name,
            'startTime' => $this->startTime,
        ];
    }

    public static function fromArray(array $data): self
    {
        $span = new self($data['name'], $data['parentSpanId'] ?? null, $data['traceId'] ?? null);
        $span->startTime = $data['startTime'] ?? $span->startTime;

        return $span;
    }
}
