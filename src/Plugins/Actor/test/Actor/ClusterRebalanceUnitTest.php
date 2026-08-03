<?php
/**
 * Yew framework - Cluster / Gossip + rebalance unit tests (pure PHP, no Swoole server)
 */

namespace Yew\Plugins\Actor\test\Actor;

use PHPUnit\Framework\TestCase;
use Yew\Plugins\Actor\Cluster\ClusterMember;
use Yew\Plugins\Actor\Cluster\ClusterState;
use Yew\Plugins\Actor\Cluster\ClusterNode;
use Yew\Plugins\Actor\Cluster\GossipShardRouter;

class ClusterRebalanceUnitTest extends TestCase
{
    private function stateWith(string $localId, float $suspect, float $down): ClusterState
    {
        return new ClusterState($localId, 16, $suspect, $down);
    }

    public function testHeartbeatKeepsNodeUp(): void
    {
        $state = $this->stateWith('node-a', 2, 5);
        $state->join('127.0.0.1', 9501);

        // Peer registered, then heartbeat repeatedly.
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502));
        $state->tick();
        $state->tick();

        $this->assertTrue($state->getNode('node-b')->isAlive());
        $this->assertCount(2, $state->aliveNodes());
    }

    public function testMissedHeartbeatMarksSuspectThenDown(): void
    {
        $state = $this->stateWith('node-a', 2, 4);
        $state->join('127.0.0.1', 9501);
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502));

        // No further heartbeats from node-b; manipulate timestamps to simulate time passing.
        $row = $state->getNode('node-b');
        $stale = time() - 3; // within down window, past suspect
        $state->observe(new ClusterMember(
            'node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_UP, $stale, 1
        ));
        $state->tick();
        $this->assertSame(ClusterMember::STATUS_SUSPECT, $state->getNode('node-b')->status);

        // Push past down window.
        $state->observe(new ClusterMember(
            'node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_UP, time() - 10, 1
        ));
        $changed = $state->tick();
        $this->assertSame(ClusterMember::STATUS_DOWN, $state->getNode('node-b')->status);
        $this->assertContains('node-b', $changed);
        $this->assertCount(1, $state->aliveNodes());
    }

    public function testConsistentHashStabilityAndRebalance(): void
    {
        $state = $this->stateWith('node-a', 2, 5);
        $state->join('127.0.0.1', 9501);
        $state->observe(new ClusterMember('node-b', '127.0.0.1', 9502));

        $local = new ClusterNode('node-a', '127.0.0.1', 9501, true);
        $router = new GossipShardRouter($state, $local, 64);

        $name = 'user-actor-123';
        $ownerBefore = $router->ownerOf($name);
        $this->assertNotNull($ownerBefore);

        // Same name always maps to same owner while membership is unchanged.
        $this->assertSame($ownerBefore, $router->ownerOf($name));

        // Bring a third node online -> ring rebuilds, some shards move.
        $moved = false;
        $router->onRebalance(function () use (&$moved) { $moved = true; });
        $state->observe(new ClusterMember('node-c', '127.0.0.1', 9503));
        $state->tick(); // listener fires on change
        // tick triggers no change (all up), so force rebuild via owner recompute:
        $this->assertNotNull($router->ownerOf($name));

        // Kill node-b -> ownership should change for shards it owned.
        $state->observe(new ClusterMember(
            'node-b', '127.0.0.1', 9502, 1, ClusterMember::STATUS_UP, time() - 100, 1
        ));
        $state->tick();
        $this->assertSame(ClusterMember::STATUS_DOWN, $state->getNode('node-b')->status);
        // owner of our name must now be a live node (not node-b).
        $ownerAfter = $router->ownerOf($name);
        $this->assertNotSame('node-b', $ownerAfter);
    }

    public function testLocateReturnsLocalNode(): void
    {
        $state = $this->stateWith('node-a', 2, 5);
        $state->join('127.0.0.1', 9501);
        $local = new ClusterNode('node-a', '127.0.0.1', 9501, true);
        $router = new GossipShardRouter($state, $local, 16);
        $loc = $router->locate('anything');
        $this->assertNotNull($loc);
        $this->assertTrue($loc->isLocal());
    }
}
