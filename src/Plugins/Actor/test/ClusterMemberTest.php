<?php

namespace Yew\Plugins\Actor\test;

use PHPUnit\Framework\TestCase;
use Yew\Cluster\State\ClusterMember;

/**
 * Offline tests for ClusterMember projection (row <-> object) and helpers.
 */
class ClusterMemberTest extends TestCase
{
    private function sampleRow(): array
    {
        return [
            'nodeId' => 'node-x', 'host' => '10.0.0.5', 'port' => 9600, 'weight' => 2,
            'status' => ClusterMember::STATUS_UP, 'incarnation' => 7, 'lastHeartbeat' => 1234,
        ];
    }

    public function testToRowThenFromRowIsStable(): void
    {
        $member = ClusterMember::fromRow($this->sampleRow());
        $row = $member->toRow();

        // toRow() is the canonical projection (includes nodeId + publicKey);
        // rehydrating from it must reproduce the same row.
        $restored = ClusterMember::fromRow($row);
        $this->assertSame($row, $restored->toRow());

        $this->assertSame('node-x', $restored->nodeId);
        $this->assertSame($member->host, $restored->host);
        $this->assertSame($member->port, $restored->port);
        $this->assertSame($member->status, $restored->status);
        $this->assertSame($member->incarnation, $restored->incarnation);
    }

    public function testEndpointAndAliveHelpers(): void
    {
        $alive = ClusterMember::fromRow($this->sampleRow());
        $this->assertSame('node-x@10.0.0.5:9600', $alive->endpoint());
        $this->assertTrue($alive->isAlive());

        $dead = ClusterMember::fromRow(array_merge($this->sampleRow(), ['status' => ClusterMember::STATUS_DOWN]));
        $this->assertFalse($dead->isAlive());
        $this->assertSame('node-x@10.0.0.5:9600', $dead->endpoint());
    }
}
