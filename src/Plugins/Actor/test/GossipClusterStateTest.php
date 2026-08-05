<?php

namespace Yew\Plugins\Actor\test;

use PHPUnit\Framework\TestCase;
use Yew\Cluster\ClusterMember;
use Yew\Cluster\GossipClusterState;

/**
 * Offline tests for GossipClusterState membership bookkeeping.
 *
 * No Swoole is required: the constructor and the membership helpers
 * (join / observe / aliveNodes / isLocal / getNode) are pure PHP. A fake
 * transport is injected purely to satisfy the internal field; it is not used
 * by the assertions here.
 */
class GossipClusterStateTest extends TestCase
{
    private GossipClusterState $state;

    protected function setUp(): void
    {
        $this->state = new GossipClusterState('node-a');
        $this->state->setTransportForTest(new FakeGossipTransport());
    }

    public function testJoinRegistersLocalNodeAndIsAlive(): void
    {
        $this->state->join('10.0.0.1', 9600, 1);

        $local = $this->state->getNode('node-a');
        $this->assertNotNull($local);
        $this->assertTrue($local->isAlive());
        $this->assertTrue($this->state->isLocal('node-a'));
        $this->assertFalse($this->state->isLocal('node-b'));
        $this->assertArrayHasKey('node-a', $this->state->aliveNodes());
    }

    public function testObserveAddsRemotePeerToAliveNodes(): void
    {
        $peer = new ClusterMember('node-b', '10.0.0.2', 9600, 1, ClusterMember::STATUS_UP, time(), 1);

        $this->state->observe($peer);

        $this->assertSame($peer, $this->state->getNode('node-b'));
        $this->assertArrayHasKey('node-b', $this->state->aliveNodes());
        $this->assertTrue($this->state->getNode('node-b')->isAlive());
    }

    public function testObserveKeepsHigherIncarnation(): void
    {
        $older = new ClusterMember('node-b', '10.0.0.2', 9600, 1, ClusterMember::STATUS_UP, time(), 5);
        $newer = new ClusterMember('node-b', '10.0.0.2', 9600, 1, ClusterMember::STATUS_UP, time(), 9);

        $this->state->observe($older);
        $this->assertSame(5, $this->state->getNode('node-b')->incarnation);

        // a stale (lower incarnation) update must NOT overwrite
        $stale = new ClusterMember('node-b', '10.0.0.2', 9600, 1, ClusterMember::STATUS_UP, time(), 3);
        $this->state->observe($stale);
        $this->assertSame(5, $this->state->getNode('node-b')->incarnation);

        // a fresher (higher incarnation) update must win
        $this->state->observe($newer);
        $this->assertSame(9, $this->state->getNode('node-b')->incarnation);
    }
}
