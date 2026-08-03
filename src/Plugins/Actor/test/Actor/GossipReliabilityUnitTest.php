<?php
/**
 * Yew framework - Gossip reliability (ACK + retransmit + missing-info pull) tests
 */

namespace Yew\Plugins\Actor\test\Actor;

use PHPUnit\Framework\TestCase;
use Yew\Plugins\Actor\Cluster\ClusterMember;
use Yew\Plugins\Actor\Cluster\GossipClusterState;
use Yew\Plugins\Actor\Cluster\GossipMessage;
use Yew\Plugins\Actor\Cluster\GossipTransport;

/**
 * Transport that can be muted to simulate packet loss: when $drop is true,
 * sendTo delivers nothing (simulating a lost packet), so we can assert the
 * sender retransmits until an ACK arrives or retries expire.
 */
class LossyGossipTransport implements GossipTransport
{
    public array $sent = [];
    public bool $drop = false;
    public ?string $lastReceived = null;

    public function broadcast(string $payload): void
    {
        if (!$this->drop) {
            $this->sent[] = $payload;
        }
    }

    public function sendTo(string $peer, string $payload): void
    {
        if (!$this->drop) {
            $this->sent[] = $payload;
        }
    }

    public function receive(float $timeout): ?string
    {
        return null;
    }
}

class GossipReliabilityUnitTest extends TestCase
{
    public function testSyncIsRetransmittedUntilAcked(): void
    {
        $transport = new LossyGossipTransport();
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('10.0.0.1', 9501);
        $state->start($transport, ['10.0.0.2:9502']);

        // First SYNC send.
        $this->assertSame(1, $state->pendingCount());

        // Simulate packet loss: retransmit fires but ACK never arrives. Tick a
        // few times past RETRY_INTERVAL; the same mid keeps getting resent.
        $transport->drop = true;
        $before = count($transport->sent);
        $now = time();
        for ($i = 1; $i <= 3; $i++) {
            $state->tick($now + $i); // each tick advances clock by 1s
        }
        $this->assertGreaterThan($before, count($transport->sent), 'SYNC must be retransmitted while unacked');

        // Now deliver an ACK for the pending mid; queue should drain.
        $pendingMid = array_key_first($state->pendingOutForTest());
        $this->assertNotNull($pendingMid);
        $state->dispatch(GossipMessage::ack('node-a', $pendingMid));

        $this->assertSame(0, $state->pendingCount(), 'ACK should clear the pending send');
    }

    public function testRetransmitGivesUpAfterMaxRetries(): void
    {
        $transport = new LossyGossipTransport();
        $transport->drop = true; // never delivered, never ACKed
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('10.0.0.1', 9501);
        $state->start($transport, ['10.0.0.2:9502']);

        $now = time();
        // Advance past MAX_RETRIES * RETRY_INTERVAL.
        for ($i = 1; $i <= 8; $i++) {
            $state->tick($now + $i);
        }
        $this->assertSame(0, $state->pendingCount(), 'Sender must give up after MAX_RETRIES');
    }

    public function testDigestWithUnknownNodeTriggersSyncPull(): void
    {
        $transport = new LossyGossipTransport();
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('10.0.0.1', 9501);
        $state->start($transport, []);

        // Peer node-b sends a DIGEST referencing node-c (unknown to us), and
        // includes its own self block so we can probe it.
        $peerSelf = new ClusterMember('node-b', '10.0.0.2', 9502, 1, ClusterMember::STATUS_UP, time(), 1);
        $digest = new GossipMessage(
            GossipMessage::DIGEST,
            'node-b',
            $peerSelf,
            ['node-c' => [ClusterMember::STATUS_UP, 1, time()]]
        );
        $state->handleDigest($digest);

        // We should have learned node-c as "unknown" AND fired a SYNC to node-b
        // to pull node-c's coordinates.
        $this->assertNotNull($state->getNode('node-c'));
        $this->assertSame('unknown', $state->getNode('node-c')->host);

        // One of the sent payloads must be a SYNC (the pull request).
        $sentSync = false;
        foreach ($transport->sent as $payload) {
            $m = GossipMessage::fromJson($payload);
            if ($m->type === GossipMessage::SYNC) {
                $sentSync = true;
            }
        }
        $this->assertTrue($sentSync, 'Missing-info digest must trigger a SYNC pull');
    }
}
