<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

/**
 * Wire format for a cross-node actor message (Akka remoting envelope /
 * Orleans grain invocation message equivalent).
 *
 * Pure data + (de)serialisation so it can be unit-tested without a network.
 * Transport-agnostic: the same envelope goes over TCP today and could go over
 * QUIC/gRPC later without touching Actor or Proxy code.
 */
class RemoteEnvelope
{
    public const KIND_TELL = 'tell';
    public const KIND_ASK = 'ask';

    public function __construct(
        public string $msgId,
        public string $kind,
        public string $actorName,
        public string $method,
        public array $arguments,
        public ?string $traceId = null,
        public ?string $fromNode = null
    ) {
    }

    public function toJson(): string
    {
        return json_encode([
            'msgId' => $this->msgId,
            'kind' => $this->kind,
            'actorName' => $this->actorName,
            'method' => $this->method,
            'arguments' => $this->arguments,
            'traceId' => $this->traceId,
            'fromNode' => $this->fromNode,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function fromJson(string $json): self
    {
        $d = json_decode($json, true);
        if (!is_array($d)) {
            throw new \InvalidArgumentException('Invalid RemoteEnvelope json');
        }
        return new self(
            (string) ($d['msgId'] ?? ''),
            (string) ($d['kind'] ?? self::KIND_TELL),
            (string) ($d['actorName'] ?? ''),
            (string) ($d['method'] ?? ''),
            (array) ($d['arguments'] ?? []),
            isset($d['traceId']) ? (string) $d['traceId'] : null,
            isset($d['fromNode']) ? (string) $d['fromNode'] : null
        );
    }
}
