<?php
/**
 * Yew framework - Gossip digest merge + failure detection tests (pure PHP)
 */

namespace Yew\Plugins\Actor\test\Actor;

use PHPUnit\Framework\TestCase;
use Yew\Plugins\Actor\Cluster\ClusterMember;
use Yew\Plugins\Actor\Cluster\GossipClusterState;
use Yew\Plugins\Actor\Cluster\GossipMessage;
use Yew\Plugins\Actor\Cluster\GossipTransport;

/**
 * In-memory GossipTransport for deterministic tests.
 */
class FakeGossipTransport implements GossipTransport
{
    public array $sent = [];

    public function broadcast(string $payload): void
    {
        $this->sent[] = ['type' => 'broadcast', 'payload' => $payload];
    }

    public function sendTo(string $peer, string $payload): void
    {
        $this->sent[] = ['type' => 'sendTo', 'peer' => $peer, 'payload' => $payload];
    }

    public function receive(float $timeout): ?string
    {
        return null;
    }
}

class GossipUnitTest extends TestCase
{
    public function testDigestRoundTrip(): void
    {
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('127.0.0.1', 9501);
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_SUSPECT, 90, 2));

        $msg = GossipMessage::digest('node-a', $state->allNodes());
        $back = GossipMessage::fromJson($msg->toJson());

        $this->assertSame(GossipMessage::DIGEST, $back->type);
        $this->assertSame('node-a', $back->fromNode);
        $this->assertSame(ClusterMember::STATUS_UP, $back->digest['node-a'][0]);
        $this->assertSame(ClusterMember::STATUS_SUSPECT, $back->digest['node-b'][0]);
    }

    public function testDigestMergePicksNewerIncarnation(): void
    {
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('127.0.0.1', 9501);
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_UP, 50, 1));
        $this->assertSame(1, $state->getNode('node-b')->incarnation);

        $msg = new GossipMessage(GossipMessage::DIGEST, 'node-c', null, [
            'node-b' => [ClusterMember::STATUS_DOWN, 5, 200],
        ]);
        $state->handleDigest($msg);
        $b = $state->getNode('node-b');
        $this->assertSame(ClusterMember::STATUS_DOWN, $b->status);
        $this->assertSame(5, $b->incarnation);
        $this->assertSame(200, $b->lastHeartbeat);
    }

    public function testDigestMergeIgnoresStaleIncarnation(): void
    {
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('127.0.0.1', 9501);
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_UP, 200, 10));

        $msg = new GossipMessage(GossipMessage::DIGEST, 'node-c', null, [
            'node-b' => [ClusterMember::STATUS_DOWN, 3, 999],
        ]);
        $state->handleDigest($msg);
        $b = $state->getNode('node-b');
        $this->assertSame(ClusterMember::STATUS_UP, $b->status);
        $this->assertSame(10, $b->incarnation);
    }

    public function testTickDetectsDown(): void
    {
        $transport = new FakeGossipTransport();
        $state = new GossipClusterState('node-a', 2, 4);
        $state->join('127.0.0.1', 9501);
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_UP, time(), 1));
        $state->start($transport, ['127.0.0.1:9502']);

        // Simulate node-b going silent past the down window.
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_UP, time() - 100, 1));
        $changed = $state->tick();

        $this->assertSame(ClusterMember::STATUS_DOWN, $state->getNode('node-b')->status);
        $this->assertContains('node-b', $changed);
        // A gossip push (digest) must have been emitted to a peer.
        $this->assertNotEmpty($transport->sent);
    }

    public function testNewNodeDiscoveredViaDigest(): void
    {
        $state = new GossipClusterState('node-a', 2, 5);
        $state->join('127.0.0.1', 9501);
        $msg = new GossipMessage(GossipMessage::DIGEST, 'node-b', null, [
            'node-c' => [ClusterMember::STATUS_UP, 1, time()],
        ]);
        $changed = [];
        $state->registerListener(function ($c) use (&$changed) { $changed = array_merge($changed, $c); });
        $state->handleDigest($msg);

        $this->assertNotNull($state->getNode('node-c'));
        $this->assertContains('node-c', $changed);
    }
}
