<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\test\Actor;

use PHPUnit\Framework\TestCase;
use Yew\Plugins\Actor\Cluster\ClusterMember;
use Yew\Plugins\Actor\Cluster\GossipClusterState;
use Yew\Plugins\Actor\Cluster\GossipMessage;
use Yew\Plugins\Actor\Cluster\NodeKey;

/**
 * Wire stub: deliver a payload to exactly one listener (the peer) and capture
 * what that peer's state emitted, so we can assert on signed wire bytes.
 */
class SecLinkedTransport
{
    private array $aToB = [];
    private array $bToA = [];

    public function sendTo(string $peer, string $payload): void
    {
        if ($peer === 'node-b') {
            $this->aToB[] = $payload;
        } else {
            $this->bToA[] = $payload;
        }
    }

    public function receiveA(): ?string
    {
        return array_shift($this->aToB);
    }

    public function receiveB(): ?string
    {
        return array_shift($this->bToA);
    }
}

class GossipSecurityUnitTest extends TestCase
{
    private function makeState(string $id, NodeKey $key, array $trust = []): GossipClusterState
    {
        $s = new GossipClusterState($id, 3, 8);
        $s->setKey($key, $trust);
        $s->join('127.0.0.1', 9500 + (int) substr($id, -1), 1);
        return $s;
    }

    public function testAsymmetricSignVerifyAcrossNodes()
    {
        $ka = NodeKey::generate();
        $kb = NodeKey::generate();
        $a = $this->makeState('node-a', $ka);
        $b = $this->makeState('node-b', $kb);

        $msg = GossipMessage::fromJson(json_encode([
            't' => GossipMessage::DIGEST, 'f' => 'node-a',
            'd' => ['node-a' => ['up', 1, 100]], 'mid' => 'm1',
        ], JSON_UNESCAPED_UNICODE));

        // A signs with its private key; B must verify using A's learned pubkey.
        $sig = $a->signForTest($msg, 1000);
        $this->assertNotEmpty($sig);
        // B auto-learns A's public key from the message envelope.
        $b->ingestForTest($msg->toJson(), 1000);
        $this->assertTrue($b->verifyForTest($msg, 1000));
        $this->assertSame($ka->getKeyId(), $msg->keyId);
    }

    public function testTamperedMessageRejected()
    {
        $ka = NodeKey::generate();
        $kb = NodeKey::generate();
        $a = $this->makeState('node-a', $ka);
        $b = $this->makeState('node-b', $kb);

        $msg = GossipMessage::fromJson(json_encode([
            't' => GossipMessage::DIGEST, 'f' => 'node-a',
            'd' => ['node-a' => ['up', 1, 100]], 'mid' => 'm2',
        ], JSON_UNESCAPED_UNICODE));
        $a->signForTest($msg, 1000);
        $b->ingestForTest($msg->toJson(), 1000);

        // Attacker flips a digest value after signing.
        $msg->digest['node-a'][2] = 999;
        $this->assertFalse($b->verifyForTest($msg, 1000));
    }

    public function testReplayOutsideClockSkewRejected()
    {
        $ka = NodeKey::generate();
        $a = $this->makeState('node-a', $ka);
        $b = $this->makeState('node-b', NodeKey::generate());

        $msg = GossipMessage::fromJson(json_encode([
            't' => GossipMessage::DIGEST, 'f' => 'node-a', 'mid' => 'm3',
        ], JSON_UNESCAPED_UNICODE));
        $a->signForTest($msg, 1000);
        $b->ingestForTest($msg->toJson(), 1000);

        // Fresh now -> ok; far future -> stale/replay rejected.
        $this->assertTrue($b->verifyForTest($msg, 1010));
        $this->assertFalse($b->verifyForTest($msg, 1000 + 60 * 60));
    }

    public function testPinnedTrustStoreRejectsUnknownNode()
    {
        $ka = NodeKey::generate();
        $kb = NodeKey::generate();
        // B pins A's real public key; a forged node with a different key is rejected.
        $b = $this->makeState('node-b', $kb, ['node-a' => $ka->getPublicKeyPem()]);

        $msg = GossipMessage::fromJson(json_encode([
            't' => GossipMessage::DIGEST, 'f' => 'node-a', 'mid' => 'm4',
        ], JSON_UNESCAPED_UNICODE));

        // Good node-a signed with the pinned key -> accepted.
        $a = $this->makeState('node-a', $ka);
        $a->signForTest($msg, 1000);
        $b->ingestForTest($msg->toJson(), 1000);
        $this->assertTrue($b->verifyForTest($msg, 1000));

        // Forged node claims to be node-a but signed with a different key -> rejected.
        $forger = $this->makeState('node-a', NodeKey::generate());
        $forger->signForTest($msg, 1000);
        $b->ingestForTest($msg->toJson(), 1000);
        $this->assertFalse($b->verifyForTest($msg, 1000));
    }

    public function testKeyRotationKeepsOldKeyValidThenExpires()
    {
        $ka = NodeKey::generate();
        $a = $this->makeState('node-a', $ka);
        $b = $this->makeState('node-b', NodeKey::generate());

        // Sign a message with the original key.
        $msg = GossipMessage::fromJson(json_encode([
            't' => GossipMessage::DIGEST, 'f' => 'node-a', 'mid' => 'm5',
        ], JSON_UNESCAPED_UNICODE));
        $a->signForTest($msg, 1000);
        $b->ingestForTest($msg->toJson(), 1000);
        $oldKeyId = $msg->keyId;

        // Rotate A's key; B should still verify the old message during keyHistory.
        $a->rotateKey();
        $this->assertNotSame($oldKeyId, $a->keyIdForTest());
        $this->assertTrue($b->verifyForTest($msg, 1000));

        // After keyHistory expires, the old key is dropped -> forward secrecy.
        $b->tick(1000 + 60 * 60 * 24);
        $this->assertFalse($b->verifyForTest($msg, 1000 + 60 * 60 * 24));
    }

    public function testLargeMessageIsFragmentedAndReassembled()
    {
        $ka = NodeKey::generate();
        $kb = NodeKey::generate();
        $a = $this->makeState('node-a', $ka);
        $b = $this->makeState('node-b', $kb);

        // Build a SYN_ACK whose full membership table exceeds one UDP datagram.
        $full = [];
        for ($i = 0; $i < 2000; $i++) {
            $full["big-node-$i"] = [
                'host' => '10.0.0.' . ($i % 255), 'port' => 10000 + $i,
                'weight' => 1, 'status' => 'up', 'incarnation' => 1, 'lastHeartbeat' => 1000,
            ];
        }
        $ack = new GossipMessage(
            GossipMessage::SYNC_ACK, 'node-a', null, [], $full, 'm-parent'
        );
        $a->signForTest($ack, 1000);

        // Emit would fragment; we emulate the wire by splitting the signed JSON
        // the same way emit() does, then feeding each fragment through ingest().
        $json = $ack->toJson();
        $this->assertGreaterThan(GossipMessage::FRAG_SIZE, strlen($json));

        $chunks = str_split($json, GossipMessage::FRAG_SIZE);
        $total = count($chunks);
        $rebuilt = null;
        foreach ($chunks as $seq => $chunk) {
            $env = json_encode([
                'env' => 1, 'f' => 'node-a', 'of' => 'm-parent',
                'seq' => $seq, 'total' => $total, 'data' => base64_encode($chunk), 'ts' => 1000,
                'kid' => $ka->getKeyId(), 'pub' => $ka->getPublicKeyPem(),
                'sig' => $ka->sign(json_encode([
                    'env' => 1, 'f' => 'node-a', 'of' => 'm-parent',
                    'seq' => $seq, 'total' => $total, 'data' => base64_encode($chunk), 'ts' => 1000,
                ], JSON_UNESCAPED_UNICODE)),
            ], JSON_UNESCAPED_UNICODE);
            $res = $b->ingestForTest($env, 1000);
            if ($res !== null) {
                $rebuilt = $res;
            }
        }
        $this->assertNotNull($rebuilt, 'final fragment should yield the complete message');
        $final = GossipMessage::fromJson($rebuilt);
        $this->assertSame('m-parent', $final->ackOf);
        $this->assertCount(2000, $final->full);
        $this->assertTrue($b->verifyForTest($final, 1000));
    }

    /**
     * Single-packet (per-fragment) retransmission: the sender keeps a fragment
     * cache; when the receiver reports missing sequence numbers via FRAG_NACK,
     * only those fragments are resent — not the whole message.
     */
    public function testPerFragmentRetransmission()
    {
        $ka = NodeKey::generate();
        // Sender "a" has a real transport that records every datagram it sends.
        $rec = new RecordingTransport();
        $a = $this->makeState('node-a', $ka);
        $a->setTransportForTest($rec);

        // Build + emit a large message so it fragments and caches chunks.
        $full = [];
        for ($i = 0; $i < 2000; $i++) {
            $full["bn-$i"] = ['host' => '10.0.0.1', 'port' => 10000 + $i,
                'weight' => 1, 'status' => 'up', 'incarnation' => 1, 'lastHeartbeat' => 1000];
        }
        $msg = new GossipMessage(GossipMessage::SYNC_ACK, 'node-a', null, [], $full, 'big-mid');
        $a->join('10.0.0.1', 9501);
        $peer = 'node-b-host:9502';
        $a->sendReliableForTest($peer, $msg, true);

        // First burst: all fragments went out.
        $firstBurst = $rec->countTo($peer);
        $this->assertGreaterThan(1, $firstBurst, 'message should have been fragmented');

        // Receiver lost fragment #1 and #3. It sends a FRAG_NACK.
        $nack = GossipMessage::fragNack('node-b', 'big-mid', [1, 3]);
        $a->dispatchForTest($nack);

        // After the NACK, only the missing fragments (2 packets) are resent.
        $afterNack = $rec->countTo($peer) - $firstBurst;
        $this->assertSame(2, $afterNack, 'only the 2 missing fragments should be resent');

        // And the resent packets are exactly sequences 1 and 3.
        $resent = $rec->lastFragments($peer, 2);
        sort($resent);
        $this->assertSame([1, 3], $resent);
    }

    /**
     * NACK-layer single-packet ACK: once the sender receives a FRAG_ACK for a
     * fragment, it marks it acknowledged and will NOT retransmit it even if a
     * (duplicate/lost-ACK) FRAG_NACK asks for it again. This closes the loop so
     * retransmission stops as soon as the receiver has the fragment.
     */
    public function testFragAckStopsRetransmission()
    {
        $ka = NodeKey::generate();
        $rec = new RecordingTransport();
        $a = $this->makeState('node-a', $ka);
        $a->setTransportForTest($rec);

        $full = [];
        for ($i = 0; $i < 2000; $i++) {
            $full["bn-$i"] = ['host' => '10.0.0.1', 'port' => 10000 + $i,
                'weight' => 1, 'status' => 'up', 'incarnation' => 1, 'lastHeartbeat' => 1000];
        }
        $msg = new GossipMessage(GossipMessage::SYNC_ACK, 'node-a', null, [], $full, 'ack-mid');
        $a->join('10.0.0.1', 9501);
        $peer = 'node-b-host:9502';
        $a->sendReliableForTest($peer, $msg, true);
        $firstBurst = $rec->countTo($peer);

        // Receiver got fragment #5 and acknowledged it.
        $ack = GossipMessage::fragAck('node-b', 'ack-mid', 5);
        $a->dispatchForTest($ack);
        $this->assertTrue($a->fragAckedForTest('ack-mid', 5), 'fragment 5 should be marked acknowledged');

        // A subsequent NACK asking for #5 again must NOT cause a retransmit.
        $dupNack = GossipMessage::fragNack('node-b', 'ack-mid', [5]);
        $a->dispatchForTest($dupNack);
        $afterDup = $rec->countTo($peer) - $firstBurst;
        $this->assertSame(0, $afterDup, 'no retransmit once fragment 5 is FRAG_ACKed');

        // But an un-acked fragment (#9) IS retransmitted on NACK.
        $nack9 = GossipMessage::fragNack('node-b', 'ack-mid', [9]);
        $a->dispatchForTest($nack9);
        $after9 = $rec->countTo($peer) - $firstBurst;
        $this->assertSame(1, $after9, 'un-acked fragment 9 is retransmitted');
        $resent = $rec->lastFragments($peer, 1);
        $this->assertSame([9], $resent);
    }
}

/**
 * Transport stub that records every datagram per peer (for retransmit asserts).
 */
class RecordingTransport implements \Yew\Plugins\Actor\Cluster\GossipTransport
{
    private array $log = [];

    public function sendTo(string $peer, string $payload): void
    {
        $this->log[$peer][] = $payload;
    }

    public function broadcast(string $payload): void
    {
        $this->log['__broadcast'][] = $payload;
    }

    public function receive(float $timeout): ?string
    {
        return null;
    }

    public function countTo(string $peer): int
    {
        return count($this->log[$peer] ?? []);
    }

    /**
     * Return the fragment sequence numbers of the last $n datagrams to a peer.
     * @return int[]
     */
    public function lastFragments(string $peer, int $n): array
    {
        $all = $this->log[$peer] ?? [];
        $tail = array_slice($all, -$n);
        $seqs = [];
        foreach ($tail as $p) {
            $d = json_decode($p, true);
            if (is_array($d) && ($d['env'] ?? 0) === 1) {
                $seqs[] = (int) $d['seq'];
            }
        }
        return $seqs;
    }
}
