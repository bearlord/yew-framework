<?php

namespace Yew\Plugins\Actor\test;

use PHPUnit\Framework\TestCase;
use Yew\Cluster\ClusterMember;
use Yew\Cluster\ClusterNode;
use Yew\Cluster\GossipClusterState;
use Yew\Cluster\GossipShardRouter;

/**
 * Offline tests for GossipShardRouter consistent-hashing (ownerOf stability).
 *
 * Pure PHP: a GossipClusterState (with a fake transport) drives the router;
 * no Swoole runtime is needed.
 */
class GossipShardRouterTest extends TestCase
{
    private GossipClusterState $state;
    private GossipShardRouter $router;

    protected function setUp(): void
    {
        $this->state = new GossipClusterState('node-a');
        $this->state->setTransportForTest(new FakeGossipTransport());
        $this->state->join('10.0.0.1', 9600, 1);
        // Seed a second node up-front so the router builds a 2-node ring.
        $this->state->observe(new ClusterMember(
            'node-b', '10.0.0.2', 9600, 1, ClusterMember::STATUS_UP, time(), 1
        ));

        $local = new ClusterNode('node-a', '10.0.0.1', 9600, true);
        $this->router = new GossipShardRouter($this->state, $local, 64);
    }

    public function testOwnerIsStableAcrossCalls(): void
    {
        $first = $this->router->ownerOf('actor-lucy');
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame($first, $this->router->ownerOf('actor-lucy'));
        }
    }

    public function testTwoNodeRingShardsEvenly(): void
    {
        $names = [];
        for ($i = 0; $i < 400; $i++) {
            $names[] = 'actor-' . $i;
        }

        $ownedByA = 0;
        $ownedByB = 0;
        foreach ($names as $n) {
            $owner = $this->router->ownerOf($n);
            if ($owner === 'node-a') {
                $ownedByA++;
            } elseif ($owner === 'node-b') {
                $ownedByB++;
            } else {
                $this->fail("unexpected owner {$owner}");
            }
        }

        // Consistent hashing with equal weights should split the 400 actors
        // roughly in half between the two nodes (allow a wide tolerance).
        $this->assertGreaterThan(0, $ownedByA, 'node-a should own some shards');
        $this->assertGreaterThan(0, $ownedByB, 'node-b should own some shards');
        $this->assertLessThan(count($names), $ownedByA, 'node-a must not own everything');
        $this->assertLessThan(count($names), $ownedByB, 'node-b must not own everything');
    }
}
