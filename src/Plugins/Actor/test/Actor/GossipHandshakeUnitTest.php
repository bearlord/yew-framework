<?php
/**
 * Yew framework - Gossip full-state handshake tests (SYN / SYN-ACK / ACK)
 */

namespace Yew\Plugins\Actor\test\Actor;

use PHPUnit\Framework\TestCase;
use Yew\Plugins\Actor\Cluster\ClusterMember;
use Yew\Plugins\Actor\Cluster\GossipClusterState;
use Yew\Plugins\Actor\Cluster\GossipMessage;
use Yew\Plugins\Actor\Cluster\GossipTransport;

/**
 * Two-way in-memory transport: sendTo(peer) delivers straight into the peer's
 * inbox, so two GossipClusterState instances can complete a real handshake.
 */
class LinkedGossipTransport implements GossipTransport
{
    private \Swoole\Channel $inbox;
    /** @var array<string,LinkedGossipTransport> */
    private array $peers = []; // address => transport

    public function __construct()
    {
        $this->inbox = new \Swoole\Channel(1024);
    }

    public function link(string $addr, LinkedGossipTransport $other): void
    {
        $this->peers[$addr] = $other;
    }

    public function broadcast(string $payload): void
    {
        foreach ($this->peers as $other) {
            $other->inbox->push($payload);
        }
    }

    public function sendTo(string $peer, string $payload): void
    {
        if (isset($this->peers[$peer])) {
            $this->peers[$peer]->inbox->push($payload);
        }
    }

    public function receive(float $timeout): ?string
    {
        $pop = $this->inbox->pop($timeout);
        return is_string($pop) ? $pop : null;
    }
}

class GossipHandshakeUnitTest extends TestCase
{
    public function testFullStateHandshakeMakesPeerRoutable(): void
    {
        // Node A (seed) at 10.0.0.1:9501, Node B (newcomer) at 10.0.0.2:9502.
        $ta = new LinkedGossipTransport();
        $tb = new LinkedGossipTransport();
        $ta->link('10.0.0.2:9502', $tb);
        $tb->link('10.0.0.1:9501', $ta);

        $a = new GossipClusterState('node-a', 2, 5);
        $a->join('10.0.0.1', 9501, 1);
        $a->start($ta, []); // A is already up, no seeds

        $b = new GossipClusterState('node-b', 2, 5);
        $b->join('10.0.0.2', 9502, 1);
        $b->start($tb, ['10.0.0.1:9501']); // B seeds A

        // Drive the handshake: B sends SYNC, A replies SYN-ACK, B replies ACK.
        // Each receive/dispatch is one message; loop until both converge.
        for ($i = 0; $i < 12; $i++) {
            $pa = $ta->receive(0.05);
            if ($pa !== null) {
                $a->dispatch(GossipMessage::fromJson($pa));
            }
            $pb = $tb->receive(0.05);
            if ($pb !== null) {
                $b->dispatch(GossipMessage::fromJson($pb));
            }
        }

        // After handshake, B must know A with its REAL host:port (not unknown).
        $aInB = $b->getNode('node-a');
        $this->assertNotNull($aInB, 'B should have discovered node-a');
        $this->assertSame('10.0.0.1', $aInB->host, 'node-a host must be real after handshake');
        $this->assertSame(9501, $aInB->port, 'node-a port must be real after handshake');

        // And A must know B with real coordinates (learned from B's SYNC self).
        $bInA = $a->getNode('node-b');
        $this->assertNotNull($bInA, 'A should have discovered node-b');
        $this->assertSame('10.0.0.2', $bInA->host);
        $this->assertSame(9502, $bInA->port);

        // Both should be routable peers (have real addresses).
        $this->assertNotEmpty($b->aliveNodes());
        $this->assertNotEmpty($a->aliveNodes());
    }

    public function testSyncCarriesFullSelfMember(): void
    {
        $t = new LinkedGossipTransport();
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('10.0.0.1', 9501, 2);

        // Build a SYNC message as a newcomer would and verify self block decodes.
        $self = new ClusterMember('node-x', '10.0.0.9', 9599, 3, ClusterMember::STATUS_UP, time(), 1);
        $sync = new GossipMessage(GossipMessage::SYNC, 'node-x', $self);
        $back = GossipMessage::fromJson($sync->toJson());

        $this->assertSame(GossipMessage::SYNC, $back->type);
        $this->assertNotNull($back->self);
        $this->assertSame('10.0.0.9', $back->self->host);
        $this->assertSame(9599, $back->self->port);
        $this->assertSame(3, $back->self->weight);
    }

    public function testSyncAckCarriesFullMembership(): void
    {
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('10.0.0.1', 9501, 1);
        $state->observe(new ClusterMember('node-b', '10.0.0.2', 9502, 1, ClusterMember::STATUS_UP, time(), 1));

        $msg = GossipMessage::fullState('node-a', $state->allNodes());
        $back = GossipMessage::fromJson($msg->toJson());

        $this->assertSame(GossipMessage::SYNC_ACK, $back->type);
        $this->assertArrayHasKey('node-a', $back->full);
        $this->assertArrayHasKey('node-b', $back->full);
        $this->assertSame('10.0.0.2', $back->full['node-b']['host']);
        $this->assertSame(9502, $back->full['node-b']['port']);
    }
}
