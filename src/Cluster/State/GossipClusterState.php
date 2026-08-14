<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

use Yew\Cluster\Transport\GossipTransport;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugins\Logger\GetLogger;

/**
 * Reliable cross-machine membership via push-pull gossip over UDP.
 *
 * On top of the SYN/SYN-ACK/ACK full-state handshake and epidemic digests,
 * this layer adds the missing reliability that raw UDP lacks:
 *
 *  1. Message ids + acknowledgements. Important sends (SYNC, SYN-ACK, the
 *     pull request) are tracked in a pending queue and re-transmitted until an
 *     ACK referencing their id arrives or retries are exhausted.
 *  2. Periodic full-state probe. Any node whose host:port we still do not know
 *     (a dropped SYNC-ACK) is periodically re-probed with SYNC until resolved.
 *  3. Missing-info pull. A DIGEST that references a node we only know as
 *     "unknown" triggers an immediate SYNC to that peer to fetch coordinates,
 *     instead of waiting passively.
 *
 * Together these make convergence deterministic under packet loss: a dropped
 * handshake or digest is repaired within a couple of gossip rounds rather than
 * relying on luck.
 *
 * Integrity: by default every outbound message is signed with the node's own
 * PRIVATE key (asymmetric, per-node "certificate"). The sender's public key and
 * a short keyId travel with the message, so receivers verify with the matching
 * public key. A static trust store may additionally pin nodeId => pubkey to
 * reject unknown nodes. Forward secrecy: keys can be rotated; old keyIds remain
 * valid for a `keyHistory` window so in-flight traffic still verifies, then the
 * old key is dropped. A legacy shared-secret (HMAC) mode is kept for backward
 * compatibility when no key is configured.
 */
class GossipClusterState implements ClusterStateInterface
{
    use GetLogger;

    private const DEFAULT_SUSPECT_AFTER = 3;   // missed heartbeats (seconds)
    private const DEFAULT_DOWN_AFTER = 8;      // missed heartbeats (seconds)
    private const RETRY_INTERVAL = 1;          // seconds between re-sends
    private const MAX_RETRIES = 5;             // give up after this many
    private const DEFAULT_CLOCK_SKEW = 30;     // seconds; replay/freshness window
    private const DEFAULT_KEY_HISTORY = 3600;  // seconds an old keyId stays valid

    private string $localNodeId;
    private float $suspectAfter;
    private float $downAfter;

    /** @var array<string,ClusterMember> */
    private array $members = [];
    private array $listeners = [];
    private ?GossipTransport $transport = null;
    private array $seeds = [];

    /**
     * Seed self-healing: a locally persisted cache of peer "host:port" endpoints
     * learned from previous runs. If every static seed is simultaneously down, a
     * restarting node can still reach the cluster by treating these cached peers
     * as temporary seeds. Only peers whose real coordinates we actually received
     * (i.e. they were alive at some point) are cached, so the cache cannot be
     * poisoned by never-seen node ids.
     */
    private ?string $peerCacheFile = null;
    private array $peerCache = [];

    /**
     * Optional cross-worker shared membership view (Swoole\Table, shared memory).
     * When set AND this process is NOT the designated gossip worker, all read
     * methods (aliveNodes/allNodes/getNode) serve from the table so every worker
     * routes against a single converged view, regardless of which worker actually
     * received the UDP gossip packets.
     * @var Table|null
     */
    private ?Table $sharedTable = null;

    /**
     * Whether THIS process is the single designated gossip worker (usually
     * worker-0). Only the gossip worker runs the UDP receiver coroutine, the
     * failure-detection ticker, and writes the shared table. Non-gossip workers
     * are pure consumers of the shared table.
     */
    private bool $isGossipWorker = true;

    public function getTransport(): ?GossipTransport
    {
        return $this->transport;
    }

    /**
     * Pending reliable sends awaiting ACK.
     * mid => ['peer'=>string,'msg'=>GossipMessage,'retries'=>int,'nextAt'=>int]
     * @var array<string,array{peer:string,msg:GossipMessage,retries:int,nextAt:int}>
     */
    private array $pendingOut = [];

    // --- Signing / trust ---
    private string $secret = '';
    private int $clockSkew = self::DEFAULT_CLOCK_SKEW;
    private ?NodeKey $key = null;
    /** @var array<string,string> nodeId => public-key PEM (pinned, optional) */
    private array $trustStore = [];
    /** @var array<string,array{pem:string,validUntil:int}> keyId => pubkey + expiry (forward secrecy) */
    private array $knownKeys = [];
    private int $keyHistory = self::DEFAULT_KEY_HISTORY;

    /** @var array<string,array{total:int,buf:array,peer:string,at:int}> reassembly buffers for fragments */
    private array $reassembly = [];
    private int $reassemblyTtl = 5; // seconds before a half-received message is dropped

    /**
     * Cache of fragments we sent, keyed by message id (fragOf). Lets us resend
     * a single missing fragment on a FRAG_NACK instead of the whole message.
     * 'acked' tracks which fragment seqs the receiver has confirmed (FRAG_ACK),
     * so we stop retransmitting them.
     * @var array<string,array{total:int,chunks:array,peer:string,at:int,fragNackAt:int,acked:array<int,bool>}>
     */
    private array $sentFrags = [];
    private int $sentFragsTtl = 30; // seconds we keep send-side fragment cache

    // --- Cross-node durable store (actor persistence replication) ---
    /**
     * Replica buffer: nodeId => (actorName => (kind => payloadJson)).
     * Holds store entries replicated from peers so a node can recover a dead
     * peer's persisted actors locally.
     * @var array<string,array<string,array<string,string>>>
     */
    private array $storeReplica = [];
    /**
     * The local replicated store. When set, inbound STORE_PUT replicas are
     * ingested into it (so a dead peer's actors can be recovered locally).
     *
     * Untyped against any concrete class on purpose: the cluster package must
     * not depend on the actor package. Only ingestReplica() is invoked.
     *
     * @var object|null
     */
    private ?object $actorStore = null;
    /**
     * How many peers an entry is replicated to (besides the owning node).
     */
    private int $replicationFactor = 2;
    /**
     * Cross-node supervision hook: invoked with a dead nodeId when it transitions
     * to DOWN, so the actor layer can resurrect that node's persisted actors
     * (failover / migration) on the surviving nodes.
     * @var callable(string):void|null
     */
    private $onNodeDown = null;

    public function __construct(
        string $localNodeId,
        float $suspectAfter = self::DEFAULT_SUSPECT_AFTER,
        float $downAfter = self::DEFAULT_DOWN_AFTER
    ) {
        $this->localNodeId = $localNodeId;
        $this->suspectAfter = $suspectAfter;
        $this->downAfter = $downAfter;
    }

    /**
     * Register this node's own identity in the membership table (host:port).
     * Carries the node's public key so peers can verify its messages.
     */
    public function join(string $host, int $port, int $weight = 1): void
    {
        $this->members[$this->localNodeId] = new ClusterMember(
            $this->localNodeId, $host, $port, true,
            ClusterMember::STATUS_UP, time(), $weight
        );
    }

    /**
     * Legacy shared-secret (HMAC) mode. Kept for backward compatibility.
     */
    public function setSecret(string $secret, int $clockSkew = self::DEFAULT_CLOCK_SKEW): void
    {
        $this->secret = $secret;
        $this->clockSkew = $clockSkew;
    }

    /**
     * Enable per-node asymmetric signing.
     *
     * @param NodeKey $key          This node's private/public key pair.
     * @param array<string,string> $trustStore Optional pinned nodeId => pubkey PEM.
     * @param int $keyHistory       Seconds an old (rotated) keyId stays valid.
     */
    public function setKey(NodeKey $key, array $trustStore = [], int $keyHistory = self::DEFAULT_KEY_HISTORY): void
    {
        $this->key = $key;
        $this->trustStore = $trustStore;
        $this->keyHistory = $keyHistory;
        // Register our own current key so peers can verify.
        $this->knownKeys[$key->getKeyId()] = [
            'pem' => $key->getPublicKeyPem(),
            'validUntil' => PHP_INT_MAX, // current key never auto-expires
        ];
        // Pinned trust store entries are always trusted.
        foreach ($trustStore as $id => $pem) {
            $this->knownKeys[NodeKey::fingerprint($pem)] = [
                'pem' => $pem,
                'validUntil' => PHP_INT_MAX,
            ];
        }
    }

    /**
     * Rotate to a fresh key pair (forward secrecy). The old keyId remains valid
     * for `keyHistory` seconds so in-flight traffic still verifies, then drops.
     */
    public function rotateKey(?NodeKey $newKey = null): void
    {
        $newKey = $newKey ?? NodeKey::generate();
        // Demote the current key to time-limited history.
        if ($this->key !== null) {
            $oldId = $this->key->getKeyId();
            if (isset($this->knownKeys[$oldId])) {
                $this->knownKeys[$oldId]['validUntil'] = time() + $this->keyHistory;
            }
        }
        $this->key = $newKey;
        $this->knownKeys[$newKey->getKeyId()] = [
            'pem' => $newKey->getPublicKeyPem(),
            'validUntil' => PHP_INT_MAX,
        ];
        // Refresh our own published public key.
        if (isset($this->members[$this->localNodeId])) {
            $this->members[$this->localNodeId]->publicKey = $newKey->getPublicKeyPem();
        }
    }

    /**
     * Canonical body over which the signature is computed (everything except sig
     * and the transport-only pub/frag fields that are not part of the contract).
     */
    private function canonicalBody(GossipMessage $msg): string
    {
        return json_encode([
            't' => $msg->type,
            'f' => $msg->fromNode,
            'self' => $msg->self === null ? null : $msg->self->toRow(),
            'd' => $msg->digest,
            'full' => $msg->full,
            'mid' => $msg->mid,
            'ack' => $msg->ackOf,
            'ts' => $msg->ts,
            'kid' => $msg->keyId,
            'sact' => $msg->storeActor,
            'sknd' => $msg->storeKind,
            'spld' => $msg->storePayload,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sign a message. Asymmetric (private key) if configured, else HMAC secret.
     * Populates $msg->sig, $msg->keyId and $msg->pub.
     */
    private function sign(GossipMessage $msg, int $now): void
    {
        $msg->ts = $now;
        if ($this->key !== null) {
            $msg->keyId = $this->key->getKeyId();
            $msg->pub = $this->key->getPublicKeyPem();
            $msg->sig = $this->key->sign($this->canonicalBody($msg));
            return;
        }
        if ($this->secret !== '') {
            $msg->keyId = null;
            $msg->pub = null;
            $msg->sig = hash_hmac('sha256', $this->canonicalBody($msg), $this->secret);
            return;
        }
        $msg->sig = null;
    }

    /**
     * Resolve the public key PEM to verify a message from $fromNode.
     * Order: pinned trust store â†?learned key by keyId â†?message-embedded pub.
     */
    private function resolvePubKey(GossipMessage $msg): ?string
    {
        if (isset($this->trustStore[$msg->fromNode])) {
            return $this->trustStore[$msg->fromNode];
        }
        if ($msg->keyId !== null && isset($this->knownKeys[$msg->keyId])) {
            if (time() <= $this->knownKeys[$msg->keyId]['validUntil']) {
                return $this->knownKeys[$msg->keyId]['pem'];
            }
            return null; // expired old key (forward secrecy enforced)
        }
        if ($msg->pub !== null && $msg->pub !== '') {
            // Learn it (time-limited) so future messages with this keyId verify.
            $fid = NodeKey::fingerprint($msg->pub);
            if (!isset($this->knownKeys[$fid])) {
                $this->knownKeys[$fid] = ['pem' => $msg->pub, 'validUntil' => time() + $this->keyHistory];
            }
            return $msg->pub;
        }
        // Fall back to the public key carried in the peer's self record.
        if ($msg->self !== null && $msg->self->publicKey !== '') {
            return $msg->self->publicKey;
        }
        $m = $this->members[$msg->fromNode] ?? null;
        return $m !== null && $m->publicKey !== '' ? $m->publicKey : null;
    }

    /**
     * Verify a message's signature and freshness. Returns true if accepted.
     */
    private function verify(GossipMessage $msg, int $now): bool
    {
        if ($this->key === null && $this->secret === '') {
            return true; // signing disabled
        }
        if ($msg->sig === null || $msg->sig === '') {
            $this->error("[gossip-verify] DROP from {$msg->fromNode}: missing sig");
            return false; // signature required but missing
        }
        if (abs($now - $msg->ts) > $this->clockSkew) {
            $this->error("[gossip-verify] DROP from {$msg->fromNode}: clockSkew now=$now ts={$msg->ts}");
            return false; // replay / stale
        }
        if ($this->key !== null) {
            $pub = $this->resolvePubKey($msg);
            if ($pub === null) {
                $this->error("[gossip-verify] DROP from {$msg->fromNode}: no trusted pub (keyId=" . ($msg->keyId ?? 'null') . " hasPub=" . ($msg->pub ? 'yes' : 'no') . ")");
                return false; // no trusted public key for this sender
            }
            $ok = NodeKey::verifyWith($pub, $this->canonicalBody($msg), $msg->sig);
            if (!$ok) {
                $this->error("[gossip-verify] DROP from {$msg->fromNode}: signature mismatch (keyId=" . ($msg->keyId ?? 'null') . ")");
            }
            return $ok;
        }
        // Legacy HMAC mode.
        $ok = hash_equals(hash_hmac('sha256', $this->canonicalBody($msg), $this->secret), (string) $msg->sig);
        if (!$ok) {
            $this->error("[gossip-verify] DROP from {$msg->fromNode}: HMAC mismatch");
        }
        return $ok;
    }

    /**
     * Sign (if a key/secret is set) and transmit a message to a peer. Large
     * messages (e.g. a full membership table) are transparently fragmented into
     * UDP-sized chunks and reassembled on the receiver side. The original, fully
     * signed message is preserved intact inside the fragments, so its integrity
     * and signature survive reassembly unaltered.
     */
    private function emit(string $peer, GossipMessage $msg, int $now): void
    {
        $this->sign($msg, $now);
        $json = $msg->toJson();
        if (strlen($json) <= GossipMessage::FRAG_SIZE) {
            $this->transport->sendTo($peer, $json);
            return;
        }
        // Fragment the signed JSON. Each chunk rides in an outer envelope that
        // is itself signed, referencing the original mid via fragOf. The inner
        // JSON (with the original sig) is reassembled verbatim on receipt.
        $of = $msg->mid ?? $this->newMsgId();
        $chunks = str_split($json, GossipMessage::FRAG_SIZE);
        $total = count($chunks);
        // Cache for per-fragment retransmission (FRAG_NACK).
        $this->sentFrags[$of] = [
            'total' => $total, 'chunks' => $chunks, 'peer' => $peer,
            'at' => $now, 'fragNackAt' => 0, 'acked' => [],
        ];
        foreach ($chunks as $seq => $chunk) {
            $this->sendFragment($peer, $of, $seq, $total, $chunk, $now);
        }
    }

    /**
     * Send (or resend) a single fragment envelope. The envelope carries its own
     * sig/keyId/pub and a frag{seq,total} marker so the receiver can reassemble.
     */
    private function sendFragment(string $peer, string $of, int $seq, int $total, string $chunk, int $now): void
    {
        $inner = json_encode([
            'env' => 1, 'f' => $this->localNodeId, 'of' => $of,
            'seq' => $seq, 'total' => $total, 'data' => base64_encode($chunk), 'ts' => $now,
        ], JSON_UNESCAPED_UNICODE);
        $env = [
            'env' => 1, 'f' => $this->localNodeId, 'of' => $of,
            'seq' => $seq, 'total' => $total, 'data' => base64_encode($chunk), 'ts' => $now,
        ];
        if ($this->key !== null) {
            $env['kid'] = $this->key->getKeyId();
            $env['pub'] = $this->key->getPublicKeyPem();
            $env['sig'] = $this->key->sign($inner);
        } elseif ($this->secret !== '') {
            $env['sig'] = hash_hmac('sha256', $inner, $this->secret);
        }
        $this->transport->sendTo($peer, json_encode($env, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Send FRAG_ACK(s) for received fragment sequence numbers to the sender,
     * so it can stop retransmitting them. No-op if we don't know the sender's
     * address or have no transport yet.
     *
     * @param string $senderId nodeId of the fragment sender
     * @param string $of       original message id (fragOf)
     * @param int[]  $seqs     received fragment sequence numbers
     */
    private function sendFragAck(string $senderId, string $of, array $seqs, int $now): void
    {
        if ($this->transport === null) {
            return;
        }
        $addr = $this->peerAddrOf($senderId);
        if ($addr === null) {
            return;
        }
        foreach ($seqs as $seq) {
            $ack = GossipMessage::fragAck($this->localNodeId, $of, $seq);
            $ack->mid = $this->newMsgId();
            $this->emit($addr, $ack, $now);
        }
    }

    /**
     * Attach the wire layer + seed peers and start the UDP receiver coroutine.
     * Sends SYNC to every seed to begin the full-state handshake.
     *
     * @param GossipTransport $transport
     * @param string[] $seeds "host:port" of known peers (hint only)
     */
    public function start(GossipTransport $transport, array $seeds = []): void
    {
        $this->transport = $transport;
        $this->seeds = $seeds;
        if (method_exists($transport, 'start')) {
            $transport->start();
        }
        goWithContext(function () {
            while ($this->transport !== null) {
                $payload = $this->transport->receive(1.0);
                if ($payload === null) {
                    continue;
                }
                // Fragment envelope? Reassemble before parsing the real message.
                $payload = $this->ingest($payload, time());
                if ($payload === null) {
                    continue; // either a fragment (buffered) or a dropped bad one
                }
                try {
                    $msg = GossipMessage::fromJson($payload);
                } catch (\Throwable $e) {
                    continue;
                }
                if (!$this->verify($msg, time())) {
                    // Drop forged / stale / replayed messages.
                    continue;
                }
                $this->dispatch($msg);
            }
        });

        // Seed self-healing: fold any previously-learned peers into the SYNC
        // targets so a node can re-join even when all static seeds are down.
        $this->loadPeerCache();
        $targets = array_unique(array_merge($this->seeds, $this->peerCache));
        foreach ($targets as $seed) {
            $this->sendSync($seed);
        }
    }

    /**
     * Point the engine at a file used to persist learned peer endpoints for
     * seed self-healing. Call before start().
     */
    public function setPeerCacheFile(string $file): void
    {
        $this->peerCacheFile = $file;
    }

    /**
     * Load previously-learned peer endpoints from disk.
     */
    private function loadPeerCache(): void
    {
        if ($this->peerCacheFile === null || !is_file($this->peerCacheFile)) {
            return;
        }
        $raw = @file_get_contents($this->peerCacheFile);
        if ($raw === false) {
            return;
        }
        $data = json_decode($raw, true);
        if (is_array($data)) {
            foreach ($data as $ep) {
                if (is_string($ep) && $ep !== '') {
                    $this->peerCache[] = $ep;
                }
            }
        }
    }

    /**
     * Persist currently-known peer endpoints (host:port) so a future cold start
     * can reach them as temporary seeds. Only peers with real coordinates are
     * stored. Written at most once per changed set to avoid fs churn.
     */
    private function flushPeerCache(): void
    {
        if ($this->peerCacheFile === null) {
            return;
        }
        $known = [];
        foreach ($this->members as $id => $m) {
            if ($id === $this->localNodeId) {
                continue;
            }
            if ($m->host !== '' && $m->host !== 'unknown' && $m->port > 0) {
                $known[] = $m->host . ':' . $m->port;
            }
        }
        $known = array_values(array_unique($known));
        sort($known);
        if ($known === $this->peerCache) {
            return; // unchanged
        }
        $this->peerCache = $known;
        $dir = dirname($this->peerCacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->peerCacheFile, json_encode($known));
    }

    /**
     * Ingest a raw payload from the wire. Returns the complete, signed GossipMessage
     * JSON when a full message (or the final fragment of one) has arrived; null if
     * the payload was a non-final fragment (buffered) or failed envelope verification.
     */
    public function ingest(string $payload, int $now): ?string
    {
        $d = json_decode($payload, true);
        if (!is_array($d) || ($d['env'] ?? 0) !== 1) {
            // Not a fragment envelope: assume it's a full GossipMessage JSON.
            return $payload;
        }
        // Fragment envelope: verify its outer signature, then buffer.
        if (isset($d['sig']) && $d['sig'] !== '') {
            $body = json_encode([
                'env' => 1, 'f' => $d['f'], 'of' => $d['of'],
                'seq' => $d['seq'], 'total' => $d['total'], 'data' => $d['data'], 'ts' => $d['ts'],
            ], JSON_UNESCAPED_UNICODE);
            if ($this->key !== null) {
                $pub = $this->resolvePubKeyFromEnv($d);
                if ($pub === null || !NodeKey::verifyWith($pub, $body, $d['sig'])) {
                    return null;
                }
            } elseif ($this->secret !== '') {
                if (!hash_equals(hash_hmac('sha256', $body, $this->secret), (string) $d['sig'])) {
                    return null;
                }
            }
        }
        $of = (string) $d['of'];
        $seq = (int) $d['seq'];
        $total = (int) $d['total'];
        if (!isset($this->reassembly[$of])) {
            $this->reassembly[$of] = ['total' => $total, 'buf' => [], 'peer' => (string) $d['f'], 'at' => $now, 'fragNackAt' => 0];
        }
        $this->reassembly[$of]['buf'][$seq] = base64_decode((string) $d['data'], true);
        if (count($this->reassembly[$of]['buf']) >= $total) {
            $full = '';
            ksort($this->reassembly[$of]['buf']);
            foreach ($this->reassembly[$of]['buf'] as $chunk) {
                $full .= $chunk;
            }
            unset($this->reassembly[$of]);
            // Whole message reassembled: confirm all fragments at once.
            $this->sendFragAck((string) $d['f'], $of, range(0, $total - 1), $now);
            return $full; // complete signed GossipMessage JSON
        }
        // Partial: acknowledge this specific fragment so the sender stops
        // retransmitting it (single-packet ACK for the NACK layer).
        $this->sendFragAck((string) $d['f'], $of, [$seq], $now);
        return null; // more fragments expected
    }

    /**
     * Resolve a public key for a fragment envelope (mirrors resolvePubKey but for
     * the env shape: kid/pub fields).
     */
    private function resolvePubKeyFromEnv(array $env): ?string
    {
        $kid = $env['kid'] ?? null;
        if ($kid !== null && isset($this->knownKeys[$kid])) {
            if (time() <= $this->knownKeys[$kid]['validUntil']) {
                return $this->knownKeys[$kid]['pem'];
            }
            return null;
        }
        $pub = $env['pub'] ?? null;
        if ($pub !== null && $pub !== '') {
            $fid = NodeKey::fingerprint($pub);
            if (!isset($this->knownKeys[$fid])) {
                $this->knownKeys[$fid] = ['pem' => $pub, 'validUntil' => time() + $this->keyHistory];
            }
            return $pub;
        }
        return null;
    }

    /**
     * Route an inbound gossip message to the right phase handler.
     */
    private function dispatch(GossipMessage $msg): void
    {
        // ACK clears the matching pending send (reliability layer).
        if ($msg->type === GossipMessage::ACK && $msg->ackOf !== null) {
            unset($this->pendingOut[$msg->ackOf]);
            // Whole message acknowledged: drop our fragment cache for it too.
            unset($this->sentFrags[$msg->ackOf]);
            return;
        }
        switch ($msg->type) {
            case GossipMessage::SYNC:
                $this->handleSync($msg);
                break;
            case GossipMessage::SYNC_ACK:
                $this->handleSyncAck($msg);
                break;
            case GossipMessage::FRAG_NACK:
                $this->handleFragNack($msg);
                break;
            case GossipMessage::FRAG_ACK:
                $this->handleFragAck($msg);
                break;
            case GossipMessage::STORE_PUT:
                $this->handleStorePut($msg);
                break;
            case GossipMessage::ACK:
                // standalone ACK (peer confirm) â€?nothing to store
                break;
            case GossipMessage::DIGEST:
            default:
                $this->handleDigest($msg);
                break;
        }
    }

    /**
     * Receiver asked us to resend specific missing fragments of a message we
     * sent earlier. We still have the chunk cache in $sentFrags, so we ship
     * only the requested fragments (per-packet retransmission, not the whole
     * message). If the cache already expired we fall back to a full resend.
     */
    private function handleFragNack(GossipMessage $msg): void
    {
        $of = $msg->fragOf;
        if ($of === null || $msg->missing === null || empty($msg->missing)) {
            return;
        }
        $cache = $this->sentFrags[$of] ?? null;
        $now = time();
        if ($cache === null) {
            // We no longer have the fragments cached; trigger a full resend via
            // the normal reliable path if this message is still pending.
            return;
        }
        foreach ($msg->missing as $seq) {
            // Skip fragments the receiver already acknowledged (FRAG_ACK): no
            // point retransmitting them, and avoids an ACK-loss retransmit loop.
            if (isset($cache['acked'][$seq]) && $cache['acked'][$seq]) {
                continue;
            }
            if (isset($cache['chunks'][$seq])) {
                $this->sendFragment($cache['peer'], $of, $seq, $cache['total'], $cache['chunks'][$seq], $now);
            }
        }
        $this->sentFrags[$of]['fragNackAt'] = $now;
    }

    /**
     * Receiver confirmed a specific fragment arrived (FRAG_ACK). Mark it so we
     * stop retransmitting it. Once every fragment is acknowledged we can drop
     * the send-side cache even before the whole-message ACK arrives.
     */
    private function handleFragAck(GossipMessage $msg): void
    {
        $of = $msg->fragOf;
        $seq = $msg->recvSeq;
        if ($of === null || $seq === null) {
            return;
        }
        if (!isset($this->sentFrags[$of])) {
            return;
        }
        $this->sentFrags[$of]['acked'][$seq] = true;
        // All fragments acknowledged? Release the cache early (the whole-message
        // ACK may still arrive later and will be a harmless no-op).
        $cache = $this->sentFrags[$of];
        if (count($cache['acked']) >= $cache['total']) {
            unset($this->sentFrags[$of]);
        }
    }

    /**
     * Inbound STORE_PUT (cross-node durability): the owning node replicated one
     * of an actor's store entries. Buffer it locally so a dead peer's persisted
     * actors can be recovered on this node during failover.
     */
    private function handleStorePut(GossipMessage $msg): void
    {
        if ($msg->storeActor === null || $msg->storeKind === null || $msg->storePayload === null) {
            return;
        }
        // Belongs to the owning (now possibly dead) node; index by owner nodeId.
        $owner = $msg->fromNode;
        $this->storeReplica[$owner][$msg->storeActor][$msg->storeKind] = $msg->storePayload;
        // Also ingest into the local cluster store so failover recovery can read
        // it straight from disk without going through the replica buffer.
        if ($this->actorStore !== null) {
            $this->actorStore->ingestReplica($msg->storeKind, $msg->storeActor, $msg->storePayload);
        }
        // Acknowledge the replica so the sender stops retransmitting it.
        if ($msg->mid !== null) {
            $addr = $this->peerAddrOf($msg->fromNode);
            if ($addr !== null) {
                $this->emit($addr, GossipMessage::ack($this->localNodeId, $msg->mid), time());
            }
        }
    }

    /**
     * Replicate one actor store entry to peers for cross-node durability.
     * Fire-and-forget via the gossip transport; lost replicas are re-pushed by
     * the actor layer's periodic re-sync (same resilience model as fragment
     * retransmission). Picks the first $replicationFactor live peers.
     */
    public function replicateStoreEntry(string $actorName, string $kind, string $payload, int $now): void
    {
        if ($this->transport === null) {
            return;
        }
        $peers = $this->peerAddresses();
        if (empty($peers)) {
            return;
        }
        $factor = min($this->replicationFactor, count($peers));
        for ($i = 0; $i < $factor; $i++) {
            $peer = $peers[$i];
            $m = GossipMessage::storePut($this->localNodeId, $actorName, $kind, $payload);
            $m->mid = $this->newMsgId();
            // Reliable send: the receiver acknowledges, so a dropped replica is
            // retransmitted until it lands (at-least-once durability).
            $this->sendReliable($peer, $m, true);
        }
    }

    /**
     * Resolve a replicated store entry for a dead peer's actor. Used by the
     * actor layer during cross-node failover to recover state. Searches every
     * owner node's replica buffer (owner nodeId is not known at read time).
     */
    public function findReplica(string $actorName, string $kind): ?string
    {
        foreach ($this->storeReplica as $owner => $actors) {
            if (isset($actors[$actorName][$kind])) {
                return $actors[$actorName][$kind];
            }
        }
        return null;
    }

    /**
     * Bind the local replicated store so inbound replicas can be ingested and
     * queried during failover recovery.
     *
     * Intentionally untyped against any concrete class: the cluster package
     * must not reference the actor package. The actor layer passes its
     * ClusterActorStore here; only the replica-ingest contract is used.
     *
     * @param object $store
     */
    public function setActorStore(object $store): void
    {
        $this->actorStore = $store;
    }

    /**
     * Actor names whose store entries were replicated from $ownerNodeId. Used by
     * the actor layer during cross-node failover to decide which actors this
     * node should now resurrect.
     *
     * @return string[]
     */
    public function getReplicatedActorNames(string $ownerNodeId): array
    {
        return array_keys($this->storeReplica[$ownerNodeId] ?? []);
    }

    /**
     * Register the cross-node supervision callback fired when a peer node goes
     * DOWN (so persisted actors can be resurrected on surviving nodes).
     *
     * @param callable(string):void $cb
     * @return void
     */
    public function onNodeDown(callable $cb): void
    {
        $this->onNodeDown = $cb;
    }

    /**
     * Number of peers a store entry is replicated to (besides the owning node).
     */
    public function setReplicationFactor(int $factor): void
    {
        $this->replicationFactor = max(1, $factor);
    }

    /**
     * Reliable send: register for ACK-based retransmission, then transmit.
     */
    private function sendReliable(string $peer, GossipMessage $msg, bool $track = true): void
    {
        if ($msg->mid === null) {
            $msg->mid = $this->newMsgId();
        }
        if ($track) {
            $this->pendingOut[$msg->mid] = [
                'peer' => $peer,
                'msg' => $msg,
                'retries' => 0,
                'nextAt' => time() + self::RETRY_INTERVAL,
            ];
        }
        $this->emit($peer, $msg, time());
    }

    /**
     * Phase 1 (outbound): announce this node to a seed with full self info.
     */
    private function sendSync(string $peer): void
    {
        $self = $this->members[$this->localNodeId] ?? null;
        if ($self === null) {
            return;
        }
        $msg = new GossipMessage(GossipMessage::SYNC, $this->localNodeId, $self);
        // SYNC is important: track it for retransmission.
        $this->sendReliable($peer, $msg, true);
    }

    /**
     * Phase 2 (inbound SYNC): record the newcomer (now with real host:port) and
     * reply with the FULL membership table (SYN-ACK). The SYN-ACK is tracked so
     * a dropped one is resent until the newcomer ACKs.
     */
    private function handleSync(GossipMessage $msg): void
    {
        if ($msg->self !== null) {
            $this->observe($msg->self);
        }
        $reply = GossipMessage::fullState($this->localNodeId, $this->members);
        $addr = $msg->self !== null ? ($msg->self->host . ':' . $msg->self->port) : null;
        if ($addr !== null) {
            // Acknowledge the SYNC first, then reliably send the full state.
            if ($msg->mid !== null) {
                $this->emit($addr, GossipMessage::ack($this->localNodeId, $msg->mid), time());
            }
            $this->sendReliable($addr, $reply, true);
        }
    }

    /**
     * Phase 3 (inbound SYN-ACK): merge the full state so every node gets a real
     * host:port, then acknowledge with ACK (clears the sender's pending entry).
     */
    private function handleSyncAck(GossipMessage $msg): void
    {
        $changed = $this->mergeFull($msg->full);
        // Acknowledge so the peer stops retransmitting the full state.
        $addr = $msg->full[$msg->fromNode]['host'] ?? null;
        $port = $msg->full[$msg->fromNode]['port'] ?? 0;
        if ($addr !== null && $port > 0) {
            if ($msg->mid !== null) {
                $this->emit($addr . ':' . $port, GossipMessage::ack($this->localNodeId, $msg->mid), time());
            }
        }
        if (!empty($changed)) {
            $this->notify($changed);
        }
    }

    /**
     * Merge a full-state payload (SYN-ACK). Fills host:port for every node and
     * resolves previously "unknown" entries. Returns changed node ids.
     *
     * @param array<string,array{host:string,port:int,weight:int,status:string,incarnation:int,lastHeartbeat:int}> $full
     * @return string[]
     */
    private function mergeFull(array $full): array
    {
        $changed = [];
        foreach ($full as $id => $row) {
            $inc = (int) $row['incarnation'];
            $hb = (int) $row['lastHeartbeat'];
            $existing = $this->members[$id] ?? null;

            if ($existing === null) {
                $this->members[$id] = new ClusterMember(
                    $id, $row['host'], (int) $row['port'], false,
                    $row['status'], $hb, (int) ($row['weight'] ?? 1)
                );
                $changed[] = $id;
                continue;
            }
            if ($existing->host === 'unknown' && $row['host'] !== 'unknown') {
                $existing->host = $row['host'];
                $existing->port = (int) $row['port'];
                $existing->weight = (int) $row['weight'];
                $changed[] = $id;
            }
            // Guard against "zombie" revival (see observe()): a DOWN node can only
            // be overwritten by a strictly higher incarnation (real restart).
            if ($existing->status === ClusterMember::STATUS_DOWN
                && $inc <= $existing->incarnation) {
                continue;
            }
            if ($inc > $existing->incarnation ||
                ($inc === $existing->incarnation && $hb > $existing->lastHeartbeat)) {
                $existing->status = $row['status'];
                $existing->incarnation = $inc;
                $existing->lastHeartbeat = $hb;
                $changed[] = $id;
            }
        }
        return $changed;
    }

    /**
     * Periodic maintenance + gossip round. Returns node ids whose status changed.
     *
     * @param int|null $now Injectable clock for deterministic tests
     * @return string[]
     */
    public function tick(?int $now = null): array
    {
        $now = $now ?? time();
        $changed = [];

        // Self heartbeat.
        if (isset($this->members[$this->localNodeId])) {
            $self = &$this->members[$this->localNodeId];
            $self->lastHeartbeat = $now;
            $self->status = ClusterMember::STATUS_UP;
        }

        // Failure detection. In the multi-worker model UDP traffic is load-balanced
        // across workers, so a single worker only periodically hears a given peer's
        // heartbeat. Basing FD on THIS worker's private $members->lastHeartbeat would
        // make every worker that happens not to receive the peer's packets decide the
        // peer is dead, and each worker would write its own (contradictory) verdict
        // into the shared table ¡ª exactly the "node-1 sees node-2 down / node-2 sees
        // node-1 suspect" split-brain we observed.
        //
        // The shared table aggregates heartbeats learned by ANY worker in this process,
        // so it is the authoritative "when did THIS process last hear the peer is alive"
        // clock. FD therefore reads the peer's heartbeat from the shared table when
        // available. We only mutate THIS worker's private $members here; the converged
        // cross-worker view is reconciled by syncToSharedTable(), which merges by
        // heartbeat freshness (a worker carrying a fresher "up" heartbeat overwrites a
        // stale "down"). That prevents a worker with a stale private clock from
        // poisoning the shared table, while still letting a genuinely-stale peer (whose
        // freshness cannot be out-argued by anyone) be marked down.
        $sharedHb = [];
        if ($this->sharedTable !== null) {
            foreach ($this->sharedTable as $id => $row) {
                $sharedHb[$id] = (int) ($row['lastHeartbeat'] ?? 0);
            }
        }

        foreach ($this->members as $id => $m) {
            if ($id === $this->localNodeId) {
                continue;
            }
            if ($m->status === ClusterMember::STATUS_DOWN) {
                continue;
            }
            // Use the freshest heartbeat this process knows about: prefer the shared
            // table (any worker) over this worker's private clock.
            $hb = $m->lastHeartbeat;
            if (isset($sharedHb[$id]) && $sharedHb[$id] > $hb) {
                $hb = $sharedHb[$id];
                $m->lastHeartbeat = $hb;
            }
            $elapsed = $now - $hb;
            $prev = $m->status;
            if ($elapsed >= $this->downAfter) {
                $m->status = ClusterMember::STATUS_DOWN;
                $changed[] = $id;
                // Cross-node supervision: a peer just died. Notify the actor layer
                // so it can fail over / resurrect this node's persisted actors.
                if ($this->onNodeDown !== null && $prev !== ClusterMember::STATUS_DOWN) {
                    ($this->onNodeDown)($id);
                }
            } elseif ($elapsed >= $this->suspectAfter) {
                $m->status = ClusterMember::STATUS_SUSPECT;
                if ($prev !== ClusterMember::STATUS_SUSPECT) {
                    $changed[] = $id;
                }
            }
        }

        // Reliability: retransmit pending sends whose deadline passed.
        $this->retransmit($now);

        // Per-fragment retransmission: if a reassembly is partially received but
        // stalled, ask the sender for just the missing fragments (instead of
        // waiting for a whole-message retransmit). Throttled by fragNackAt.
        foreach ($this->reassembly as $of => $rb) {
            if ($now - $rb['at'] > $this->reassemblyTtl) {
                unset($this->reassembly[$of]);
                continue;
            }
            $got = count($rb['buf']);
            if ($got >= $rb['total']) {
                continue; // already complete (will be dispatched)
            }
            if ($now - $rb['at'] < GossipMessage::FRAG_NACK_DELAY) {
                continue; // give the rest of the fragments a moment to arrive
            }
            if (isset($this->reassembly[$of]['fragNackAt']) &&
                $now - $this->reassembly[$of]['fragNackAt'] < self::RETRY_INTERVAL) {
                continue; // already asked recently
            }
            // Build the missing-sequence list.
            $missing = [];
            for ($i = 0; $i < $rb['total']; $i++) {
                if (!isset($rb['buf'][$i])) {
                    $missing[] = $i;
                }
            }
            if (empty($missing)) {
                continue;
            }
            // Resolve the sender's address from its nodeId (the fragment's `f`).
            $senderId = $rb['peer'];
            $addr = $this->peerAddrOf($senderId);
            if ($addr !== null) {
                $nack = GossipMessage::fragNack($this->localNodeId, $of, $missing);
                $nack->mid = $this->newMsgId();
                $this->emit($addr, $nack, $now);
                $this->reassembly[$of]['fragNackAt'] = $now;
            }
        }

        // Drop expired send-side fragment caches.
        foreach ($this->sentFrags as $of => $sf) {
            if ($now - $sf['at'] > $this->sentFragsTtl) {
                unset($this->sentFrags[$of]);
            }
        }

        // Probe any node we still only know as "unknown" (dropped SYNC-ACK).
        foreach ($this->members as $id => $m) {
            if ($id === $this->localNodeId || $m->host !== 'unknown' || $m->port <= 0) {
                continue;
            }
            // We have a port but unknown host â€?cannot probe; skip.
        }
        // Re-SYN seeds if handshake still incomplete (pending SYNC not ACKed).
        foreach ($this->pendingOut as $mid => $p) {
            // pendingOut already covers SYNC/SYN-ACK retries via retransmit().
        }

        // Steady-state digest push to a random peer.
        $this->gossipRound($now);

        // Persist learned peer endpoints for seed self-healing on next cold start.
        $this->flushPeerCache();

        if (!empty($changed)) {
            $this->notify($changed);
        }
        return $changed;
    }

    /**
     * Retransmit (or drop) pending reliable sends based on the clock.
     */
    private function retransmit(int $now): void
    {
        foreach ($this->pendingOut as $mid => $p) {
            if ($now < $p['nextAt']) {
                continue;
            }
            if ($p['retries'] >= self::MAX_RETRIES) {
                // Give up; the peer is likely unreachable. Mark unknown host as down.
                unset($this->pendingOut[$mid]);
                continue;
            }
            $this->emit($p['peer'], $p['msg'], $now); // re-signs with fresh ts
            $this->pendingOut[$mid]['retries']++;
            $this->pendingOut[$mid]['nextAt'] = $now + self::RETRY_INTERVAL;
        }
    }

    /**
     * Anti-entropy merge of a DIGEST (cheap, status/incarnation/heartbeat only).
     * If the digest references a node we only know as "unknown", fire a SYNC to
     * that peer to pull its full coordinates (missing-info repair).
     */
    public function handleDigest(GossipMessage $msg): void
    {
        $this->error("[gossip-digest] from {$msg->fromNode} entries=" . count($msg->digest) . " self=" . ($msg->self ? $msg->self->host . ':' . $msg->self->port : 'null') . " membersNow=" . count($this->members));
        foreach ($msg->digest as $id => [$status, $inc, $hb]) {
            $inc = (int) $inc;
            $hb = (int) $hb;
            $existing = $this->members[$id] ?? null;
            if ($existing === null) {
                $this->members[$id] = new ClusterMember(
                    $id, 'unknown', 0, false, $status, $hb, 1
                );
                // We learned of a node but lack its address. The sender attached
                // its own coordinates in $msg->self, so observe() fills them in
                // immediately instead of waiting for a SYNC round-trip.
                if ($msg->self !== null) {
                    $this->observe($msg->self);
                    $this->sendSync($msg->self->host . ':' . $msg->self->port);
                }
                $this->notify([$id]);
                continue;
            }
            // Guard against "zombie" revival (see observe()): once a node is DOWN,
            // only a strictly higher incarnation (real restart) may overwrite it.
            if ($existing->status === ClusterMember::STATUS_DOWN
                && $inc <= $existing->incarnation) {
                continue;
            }
            if ($inc > $existing->incarnation ||
                ($inc === $existing->incarnation && $hb > $existing->lastHeartbeat)) {
                $existing->status = $status;
                $existing->incarnation = $inc;
                $existing->lastHeartbeat = $hb;
            }
        }
    }

    /**
     * Pick a peer (live member with real host:port, or seed) and push a digest.
     */
    private function gossipRound(int $now): void
    {
        if ($this->transport === null) {
            return;
        }
        $msg = GossipMessage::digest($this->localNodeId, $this->members);
        $peers = $this->peerAddresses();
        if (empty($peers)) {
            return;
        }
        $target = $peers[array_rand($peers)];
        // Digests are fire-and-forget (epidemic); not tracked for retransmit.
        $this->emit($target, $msg, $now);
    }

    /**
     * Addresses of known peers: alive members with real host:port + seeds.
     *
     * @return string[]
     */
    private function peerAddresses(): array
    {
        $out = [];
        foreach ($this->members as $m) {
            if ($m->nodeId === $this->localNodeId) {
                continue;
            }
            if ($m->host !== 'unknown' && $m->port > 0) {
                $out[] = $m->host . ':' . $m->port;
            }
        }
        foreach ($this->seeds as $s) {
            if (!in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    /**
     * Resolve a single member's host:port address (or null if unknown).
     */
    private function peerAddrOf(string $nodeId): ?string
    {
        $m = $this->members[$nodeId] ?? null;
        if ($m !== null && $m->host !== 'unknown' && $m->port > 0) {
            return $m->host . ':' . $m->port;
        }
        return null;
    }

    /**
     * Record a heartbeat/state received for a peer (used when a peer's full
     * ClusterMember is known, e.g. from SYNC).
     */
    public function observe(ClusterMember $member): void
    {
        $existing = $this->members[$member->nodeId] ?? null;
        if ($existing !== null && $existing->incarnation > $member->incarnation) {
            return;
        }
        // Guard against "zombie" revival: once a node has been declared DOWN by
        // the failure detector, a stale/late/replayed heartbeat (same or lower
        // incarnation) must NOT resurrect it. Only a strictly higher incarnation
        // (i.e. the peer actually restarted and bumped its incarnation) is
        // allowed to overwrite the DOWN state. This prevents a dead node from
        // being shown as UP forever just because one old UDP packet arrived
        // after the FD timeout.
        if ($existing !== null && $existing->status === ClusterMember::STATUS_DOWN
            && $member->incarnation <= $existing->incarnation) {
            return;
        }
        $member->lastHeartbeat = time();
        if ($member->status === ClusterMember::STATUS_SUSPECT && $existing !== null
            && $existing->status !== ClusterMember::STATUS_DOWN) {
            $member->status = ClusterMember::STATUS_UP;
        }
        $this->members[$member->nodeId] = $member;
    }

    /**
     * All members currently considered alive (UP/SUSPECT).
     *
     * @return ClusterMember[]
     */
    public function aliveNodes(): array
    {
        if ($this->sharedTable !== null) {
            $out = [];
            foreach ($this->readSharedNodes() as $id => $m) {
                if ($m->isAlive()) {
                    $out[$id] = $m;
                }
            }
            return $out;
        }
        $out = [];
        foreach ($this->members as $id => $m) {
            if ($m->isAlive()) {
                $out[$id] = $m;
            }
        }
        return $out;
    }

    /**
     * Every known member, alive or not.
     *
     * @return ClusterMember[]
     */
    public function allNodes(): array
    {
        if ($this->sharedTable !== null) {
            return $this->readSharedNodes();
        }
        return $this->members;
    }

    /**
     * Look up a single member by node id.
     *
     * @param string $nodeId
     * @return ClusterMember|null
     */
    public function getNode(string $nodeId): ?ClusterMember
    {
        if ($this->sharedTable !== null) {
            $row = $this->sharedTable->get($nodeId);
            return $row === false ? null : ClusterMember::fromRow($row);
        }
        return $this->members[$nodeId] ?? null;
    }

    /**
     * Whether the given node id is this node.
     *
     * @param string $nodeId
     * @return bool
     */
    public function isLocal(string $nodeId): bool
    {
        return $nodeId === $this->localNodeId;
    }

    /**
     * This node's id.
     *
     * @return string
     */
    public function getLocalNodeId(): string
    {
        return $this->localNodeId;
    }

    /**
     * Register a callback invoked when membership changes.
     *
     * @param callable $cb
     */
    public function registerListener(callable $cb): void
    {
        $this->listeners[] = $cb;
    }

    /**
     * Number of sends still awaiting ACK (exposed for tests/observability).
     */
    public function pendingCount(): int
    {
        return count($this->pendingOut);
    }

    private function notify(array $changed): void
    {
        $this->syncToSharedTable();
        foreach ($this->listeners as $cb) {
            $cb($changed, $this);
        }
    }

    /**
     * Reconcile this worker's router ring with the converged shared view.
     *
     * In the multi-worker model every worker runs the gossip receiver, but UDP
     * traffic is load-balanced so a single worker only ever sees a slice of the
     * membership updates. Each worker therefore converges its own $members only
     * partially; the shared table aggregates what ANY worker learned. Routing in
     * every worker must reflect the SHARED view, not just this worker's partial
     * private one ¡ª otherwise a worker whose slice never included node-2 keeps a
     * ring of size 1 and routes every "remote" actor back to itself (500 / wrong
     * owner). We merge the shared rows into $members (without clobbering known
     * good endpoints with "unknown") and fire the membership listeners so the
     * router rebuilds its ring from the now-complete set.
     *
     * This must run on EVERY worker, gossip or not, so it must NOT short-circuit
     * on isGossipWorker (the old guard made every worker skip it).
     */
    public function refreshView(): void
    {
        if ($this->sharedTable !== null) {
            foreach ($this->readSharedNodes() as $id => $m) {
                // Only pull a shared row if it carries a usable endpoint, or if we
                // don't already have a better (non-unknown) one locally. Never let a
                // "unknown" shared row overwrite a correct local endpoint.
                if (!isset($this->members[$id])) {
                    if ($m->host !== '' && $m->host !== 'unknown' && $m->port > 0) {
                        $this->members[$id] = $m;
                    }
                } else {
                    $existing = $this->members[$id];
                    if (($existing->host === '' || $existing->host === 'unknown' || $existing->port <= 0)
                        && $m->host !== '' && $m->host !== 'unknown' && $m->port > 0) {
                        $this->members[$id] = $m;
                    }
                }
            }
        }
        foreach ($this->listeners as $cb) {
            $cb([], $this);
        }
    }

    /**
     * Read every member from the shared cross-worker table.
     * @return array<string,ClusterMember>
     */
    private function readSharedNodes(): array
    {
        $out = [];
        foreach ($this->sharedTable as $id => $row) {
            $out[$id] = ClusterMember::fromRow($row);
        }
        return $out;
    }

    /**
     * Push the local (gossip worker) converged membership into the shared
     * cross-worker table so every other worker routes against one view.
     * Only called on the designated gossip worker.
     */
    public function syncToSharedTable(): void
    {
        // NOTE: In the single-authority architecture the cluster-state process is
        // the ONLY writer of membership; there is no cross-worker Swoole\Table to
        // sync into. This method is therefore a no-op when no sharedTable was
        // configured (the normal case now). Kept so the FD notify() path stays
        // unchanged.
        if ($this->sharedTable === null) {
            return;
        }
        // Every worker that receives gossip packets merges them into its local
        // $members and here pushes the rows it knows into the shared table. The
        // table therefore aggregates members learned by ANY worker, so routing
        // sees a complete, converged view even though each worker only receives
        // a slice of the UDP traffic. We only SET (never DEL): a row absent from
        // this worker's local view may well be known to another worker, and a
        // departed node is reflected via its status (suspect/down), not by
        // physical removal, which would race across workers.
        //
        // Skip rows whose host is still "unknown" / port unset. A worker that has
        // only learned a peer's nodeId (via a digest) but not yet its real
        // endpoint would otherwise overwrite a correct row written by another
        // worker with an unusable "unknown" address, breaking cross-node TCP.
        foreach ($this->members as $id => $m) {
            if ($m->host === '' || $m->host === 'unknown' || $m->port <= 0) {
                continue;
            }
            $row = $m->toRow();
            // Merge by FRESHNESS, not by severity. UDP traffic is load-balanced across
            // workers, so a worker that hasn't recently heard a peer may still see it
            // as "up" in its private view while another worker ¡ª which also hasn't
            // heard it ¡ª has already declared it "down". Blindly keeping the "worse"
            // status (our previous logic) let a single mistaken down/suspect verdict
            // permanently poison the shared table; a worker that DID hear a fresh
            // heartbeat (higher lastHeartbeat) could never resurrect the peer.
            //
            // The peer's lastHeartbeat is the ground truth: whichever worker holds the
            // NEWEST evidence about the peer wins. A worker carrying a fresh "up"
            // heartbeat overwrites a stale "down" (resurrection). A worker carrying a
            // stale private view (older hb) cannot overwrite a fresher shared row, so
            // mistaken local verdicts no longer pollute the converged view.
            if ($table->exist($id)) {
                $existing = $table->get($id);
                if ($existing !== false) {
                    $existingHb = (int) ($existing['lastHeartbeat'] ?? 0);
                    $localHb = (int) ($row['lastHeartbeat'] ?? 0);
                    if ($existingHb > $localHb) {
                        // Shared row is newer ¡ª keep it (status, host, port, hb).
                        $row['status'] = $existing['status'];
                        $row['host'] = $existing['host'];
                        $row['port'] = $existing['port'];
                        $row['lastHeartbeat'] = $existingHb;
                    }
                    // else: local evidence is at least as fresh ¡ª use our row as-is.
                }
            }
            $table->set($id, $row);
        }
    }

    /**
     * Attach the shared cross-worker table and declare this process's role.
     * Called by ClusterPlugin after construction.
     */
    public function configureSharedView(Table $table, bool $isGossipWorker): void
    {
        $this->sharedTable = $table;
        $this->isGossipWorker = $isGossipWorker;
    }

    private function newMsgId(): string
    {
        return uniqid('gm-', true);
    }

    /**
     * Diagnostics only: return the private per-worker $members view plus whether
     * routing is currently served from the shared cross-worker table. Lets the
     * diag endpoint compare the two sources so it can tell a real routing failure
     * apart from "this worker's private view just hasn't learned the peer yet".
     *
     * @return array{nodeId:string, sharedView:bool, privateMembers:array, sharedMembers:array}
     */
    public function debugState(): array
    {
        $shared = [];
        if ($this->sharedTable !== null) {
            foreach ($this->readSharedNodes() as $id => $m) {
                $shared[$id] = $m->toRow();
            }
        }
        $private = [];
        foreach ($this->members as $id => $m) {
            $private[$id] = $m->toRow();
        }
        return [
            'nodeId' => $this->localNodeId,
            'sharedView' => $this->sharedTable !== null,
            'privateMembers' => $private,
            'sharedMembers' => $shared,
        ];
    }
}
