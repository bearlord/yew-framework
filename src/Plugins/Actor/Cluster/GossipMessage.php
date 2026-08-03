<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

/**
 * Unified gossip wire envelope carrying one of three phases of the
 * SYN / SYN-ACK / ACK full-state exchange, plus the periodic digest push.
 *
 *  - SYNC:    a joining node announces itself with its full ClusterMember
 *             (host:port known) and asks peers for the full membership.
 *  - SYNC_ACK: a peer replies with the FULL membership table (host:port for
 *             every node) so the newcomer can route to anyone.
 *  - ACK:     the newcomer confirms receipt; peers then treat it as live.
 *  - DIGEST:  the steady-state periodic push of [status,incarnation,heartbeat]
 *             digests (cheap, no host:port needed because routing info already
 *             converged).
 *
 * Security: every message is signed with the sender node's *private* key
 * (asymmetric). The sender's public key PEM (`pub`) and a short `keyId`
 * (fingerprint) travel with the message; receivers verify with the matching
 * public key. A static trust store may additionally pin nodeId => pubkey.
 *
 * Carrying host:port in SYNC/SYNC-ACK is what makes discovery complete: after
 * the handshake every node can build real peer addresses, not just "unknown".
 */
class GossipMessage
{
    public const SYNC = 'sync';
    public const SYNC_ACK = 'sync_ack';
    public const ACK = 'ack';
    public const DIGEST = 'digest';
    public const FRAG_NACK = 'frag_nack'; // receiver asks sender to resend missing fragments
    public const FRAG_ACK = 'frag_ack';   // receiver confirms a single fragment arrived
    public const STORE_PUT = 'store_put'; // replicate one actor store entry to peers (cross-node durability)

    // Fragmentation: a large SYN-ACK (full membership) is split into chunks.
    public const FRAG_SIZE = 60000; // bytes per UDP fragment (under 64KB)
    // Receiver waits this long after the first fragment before asking for the
    // missing ones (per-fragment retransmission). Below this, a whole-message
    // retransmit (ACK-driven) is the cheaper path.
    public const FRAG_NACK_DELAY = 1; // seconds

    public function __construct(
        public string $type,
        public string $fromNode,
        public ?ClusterMember $self = null,
        /** @var array<string,array{0:string,1:int,2:int}> */
        public array $digest = [],
        /** @var array<string,array{host:string,port:int,weight:int,status:string,incarnation:int,lastHeartbeat:int}> */
        public array $full = [],
        public ?string $mid = null,
        public ?string $ackOf = null,
        public ?string $sig = null,
        public int $ts = 0,
        /**
         * Public-key fingerprint of the signing key. Lets the receiver pick the
         * right public key when keys are rotated (forward secrecy).
         */
        public ?string $keyId = null,
        /**
         * PEM-encoded public key of the sender. Carried so peers can verify
         * without a pre-shared trust store (authenticated discovery).
         */
        public ?string $pub = null,
        /**
         * Fragment metadata. `fragOf` references the original message id when
         * this envelope is one chunk of a larger message.
         * @var array{seq:int,total:int}|null
         */
        public ?array $frag = null,
        public ?string $fragOf = null,
        /**
         * For FRAG_NACK: list of missing fragment sequence numbers the receiver
         * wants the sender to resend (per-packet retransmission).
         * @var int[]|null
         */
        public ?array $missing = null,
        /**
         * For FRAG_ACK: the single fragment sequence number just received and
         * confirmed by the receiver (per-packet acknowledgement).
         */
        public ?int $recvSeq = null,
        /**
         * For STORE_PUT (cross-node durability): which actor the entry belongs to.
         */
        public ?string $storeActor = null,
        /**
         * For STORE_PUT: entry kind — 'events' | 'snapshots' | 'clear'.
         */
        public ?string $storeKind = null,
        /**
         * For STORE_PUT: JSON payload of the replicated entry.
         */
        public ?string $storePayload = null
    ) {
    }

    public function toJson(): string
    {
        return json_encode([
            't' => $this->type,
            'f' => $this->fromNode,
            'self' => $this->self === null ? null : $this->self->toRow(),
            'd' => $this->digest,
            'full' => $this->full,
            'mid' => $this->mid,
            'ack' => $this->ackOf,
            'ts' => $this->ts,
            'sig' => $this->sig,
            'kid' => $this->keyId,
            'pub' => $this->pub,
            'frag' => $this->frag,
            'fragOf' => $this->fragOf,
            'miss' => $this->missing,
            'rseq' => $this->recvSeq,
            'sact' => $this->storeActor,
            'sknd' => $this->storeKind,
            'spld' => $this->storePayload,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function fromJson(string $json): self
    {
        $d = json_decode($json, true);
        if (!is_array($d)) {
            throw new \InvalidArgumentException('Invalid GossipMessage');
        }
        $self = isset($d['self']) && is_array($d['self']) ? ClusterMember::fromRow($d['self']) : null;
        return new self(
            (string) ($d['t'] ?? self::DIGEST),
            (string) ($d['f'] ?? ''),
            $self,
            (array) ($d['d'] ?? []),
            (array) ($d['full'] ?? []),
            isset($d['mid']) ? (string) $d['mid'] : null,
            isset($d['ack']) ? (string) $d['ack'] : null,
            isset($d['sig']) ? (string) $d['sig'] : null,
            (int) ($d['ts'] ?? 0),
            isset($d['kid']) ? (string) $d['kid'] : null,
            isset($d['pub']) ? (string) $d['pub'] : null,
            isset($d['frag']) && is_array($d['frag']) ? $d['frag'] : null,
            isset($d['fragOf']) ? (string) $d['fragOf'] : null,
            isset($d['miss']) && is_array($d['miss']) ? array_map('intval', $d['miss']) : null,
            isset($d['rseq']) ? (int) $d['rseq'] : null,
            isset($d['sact']) ? (string) $d['sact'] : null,
            isset($d['sknd']) ? (string) $d['sknd'] : null,
            isset($d['spld']) ? (string) $d['spld'] : null
        );
    }

    /**
     * A short confirmation referencing another message's id.
     */
    public static function ack(string $fromNode, string $ackOf): self
    {
        return new self(self::ACK, $fromNode, null, [], [], null, $ackOf);
    }

    /**
     * Receiver → sender: "I got fragments of message $of but I'm missing these
     * sequence numbers; please resend just those." Per-packet retransmission.
     */
    public static function fragNack(string $fromNode, string $of, array $missing): self
    {
        $m = new self(self::FRAG_NACK, $fromNode);
        $m->fragOf = $of;
        $m->missing = $missing;
        return $m;
    }

    /**
     * Receiver → sender: "I just received fragment $seq of message $of." Per-packet
     * acknowledgement so the sender can stop retransmitting that fragment.
     */
    public static function fragAck(string $fromNode, string $of, int $seq): self
    {
        $m = new self(self::FRAG_ACK, $fromNode);
        $m->fragOf = $of;
        $m->recvSeq = $seq;
        return $m;
    }

    /**
     * Build a DIGEST message from the local membership table.
     */
    public static function digest(string $fromNode, array $members): self
    {
        $entries = [];
        foreach ($members as $id => $m) {
            $entries[$id] = [$m->status, $m->incarnation, $m->lastHeartbeat];
        }
        return new self(self::DIGEST, $fromNode, null, $entries);
    }

    /**
     * Replicate one actor store entry (event/snapshot/clear) to peer nodes for
     * cross-node durability. Signed like every other gossip message.
     */
    public static function storePut(string $fromNode, string $actorName, string $kind, string $payload): self
    {
        $m = new self(self::STORE_PUT, $fromNode);
        $m->storeActor = $actorName;
        $m->storeKind = $kind;
        $m->storePayload = $payload;
        return $m;
    }

    /**
     * Build a full-state message (SYNC_ACK) from the local membership table.
     *
     * @param array<string,ClusterMember> $members
     */
    public static function fullState(string $fromNode, array $members): self
    {
        $full = [];
        foreach ($members as $id => $m) {
            $full[$id] = [
                'host' => $m->host,
                'port' => $m->port,
                'weight' => $m->weight,
                'status' => $m->status,
                'incarnation' => $m->incarnation,
                'lastHeartbeat' => $m->lastHeartbeat,
            ];
        }
        return new self(self::SYNC_ACK, $fromNode, null, [], $full);
    }
}
