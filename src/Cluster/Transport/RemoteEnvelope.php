<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Transport;

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
    public const KIND_CREATE = 'create';

    /**
     * Create a cross-node actor message envelope.
     *
     * @param string $msgId Unique message id (matched against the ask reply)
     * @param string $kind One of KIND_TELL / KIND_ASK / KIND_CREATE
     * @param string $actorName Target actor name
     * @param string $method Actor method to invoke
     * @param array $arguments Method arguments
     * @param string|null $traceId Trace id for cross-node propagation
     * @param string|null $fromNode Originating node id
     * @param string|null $className Actor class name (KIND_CREATE only)
     * @param array $actorData Constructor data (KIND_CREATE only)
     * @param string|null $parent Parent actor name (KIND_CREATE only)
     */
    public function __construct(
        public string $msgId,
        public string $kind,
        public string $actorName,
        public string $method,
        public array $arguments,
        public ?string $traceId = null,
        public ?string $fromNode = null,
        public ?string $className = null,
        public array $actorData = [],
        public ?string $parent = null
    ) {
    }

    /**
     * Serialise to a single-line JSON string.
     *
     * @return string
     */
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
            'className' => $this->className,
            'actorData' => $this->actorData,
            'parent' => $this->parent,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode a JSON string into an envelope.
     *
     * @param string $json
     * @return self
     * @throws \InvalidArgumentException When the payload is not valid JSON
     */
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
            isset($d['fromNode']) ? (string) $d['fromNode'] : null,
            isset($d['className']) ? (string) $d['className'] : null,
            (array) ($d['actorData'] ?? []),
            isset($d['parent']) ? (string) $d['parent'] : null
        );
    }
}
