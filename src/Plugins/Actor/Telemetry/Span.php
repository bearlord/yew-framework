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

    /**
     * Create a span, generating ids when not supplied.
     *
     * @param string $name Human-readable span name
     * @param string|null $parentSpanId Parent span id for nesting, or null
     * @param string|null $traceId Trace id to join, or null to start a new trace
     */
    public function __construct(string $name, ?string $parentSpanId = null, ?string $traceId = null)
    {
        $this->traceId = $traceId ?? self::generateId();
        $this->spanId = self::generateId();
        $this->parentSpanId = $parentSpanId;
        $this->name = $name;
        $this->startTime = microtime(true);
    }

    /**
     * Generate a random 16-hex-char id.
     *
     * @return string
     */
    public static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Trace id this span belongs to.
     *
     * @return string
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /**
     * This span's id.
     *
     * @return string
     */
    public function getSpanId(): string
    {
        return $this->spanId;
    }

    /**
     * Parent span id, if nested.
     *
     * @return string|null
     */
    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    /**
     * Span name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Mark the span ended (records end time).
     */
    public function end(): void
    {
        $this->endTime = microtime(true);
    }

    /**
     * Elapsed seconds, or null if not yet ended.
     *
     * @return float|null
     */
    public function durationSeconds(): ?float
    {
        return $this->endTime === null ? null : ($this->endTime - $this->startTime);
    }

    /**
     * Span start time (seconds).
     *
     * @return float
     */
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

    /**
     * Rebuild a span from its IPC serialised form.
     *
     * @param array $data Decoded span array
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $span = new self($data['name'], $data['parentSpanId'] ?? null, $data['traceId'] ?? null);
        $span->startTime = $data['startTime'] ?? $span->startTime;

        return $span;
    }
}
