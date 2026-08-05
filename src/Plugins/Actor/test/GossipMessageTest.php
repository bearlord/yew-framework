<?php

namespace Yew\Plugins\Actor\test;

use PHPUnit\Framework\TestCase;
use Yew\Cluster\ClusterMember;
use Yew\Cluster\GossipMessage;

/**
 * Offline tests for GossipMessage wire-format (de)serialization.
 *
 * These do not touch Swoole/UDP; they only verify that a message survives a
 * JSON round-trip and that the typed factory helpers build correct envelopes.
 */
class GossipMessageTest extends TestCase
{
    public function testDigestRoundTripPreservesMembers(): void
    {
        $members = [
            'node-a' => ClusterMember::fromRow([
                'nodeId' => 'node-a', 'host' => '10.0.0.1', 'port' => 9600, 'weight' => 1,
                'status' => ClusterMember::STATUS_UP, 'incarnation' => 3, 'lastHeartbeat' => 1000,
            ]),
            'node-b' => ClusterMember::fromRow([
                'nodeId' => 'node-b', 'host' => '10.0.0.2', 'port' => 9600, 'weight' => 1,
                'status' => ClusterMember::STATUS_UP, 'incarnation' => 1, 'lastHeartbeat' => 1001,
            ]),
        ];

        $msg = GossipMessage::digest('node-a', $members);
        $json = $msg->toJson();

        $restored = GossipMessage::fromJson($json);

        $this->assertSame(GossipMessage::DIGEST, $restored->type);
        $this->assertSame('node-a', $restored->fromNode);
        $this->assertArrayHasKey('node-a', $restored->digest);
        $this->assertArrayHasKey('node-b', $restored->digest);
        $this->assertSame(ClusterMember::STATUS_UP, $restored->digest['node-a'][0]);
        $this->assertSame(3, $restored->digest['node-a'][1]);
    }

    public function testAckFactoryReferencesOriginalMessage(): void
    {
        $ack = GossipMessage::ack('node-b', 'msg-123');

        $this->assertSame(GossipMessage::ACK, $ack->type);
        $this->assertSame('node-b', $ack->fromNode);
        $this->assertSame('msg-123', $ack->ackOf);
        // ack carries no membership digest
        $this->assertSame([], $ack->digest);
    }

    public function testInvalidJsonThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GossipMessage::fromJson('not-json');
    }
}
