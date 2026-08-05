<?php

namespace Yew\Plugins\Actor\test;

use PHPUnit\Framework\TestCase;
use Yew\Cluster\UdpGossipTransport;

/**
 * Tests for the framework-managed (socket-less) path of UdpGossipTransport.
 *
 * The constructor allocates a \Swoole\Channel inbox; when the real Swoole
 * extension is absent the test bootstrap installs an in-memory stand-in, so
 * this test runs everywhere (Windows CLI and Linux CI alike).
 */
class UdpGossipTransportTest extends TestCase
{
    private UdpGossipTransport $transport;
    /** @var array<int,array{host:string,port:int,payload:string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->sent = [];
        $this->transport = new UdpGossipTransport('127.0.0.1', 9700, '239.0.0.1:9700');
        $this->transport->setManaged(true);
        $this->transport->setSender(function (string $host, int $port, string $payload): void {
            $this->sent[] = ['host' => $host, 'port' => $port, 'payload' => $payload];
        });
    }

    public function testBroadcastUsesInjectedSender(): void
    {
        $this->transport->broadcast('hello-digest');

        $this->assertCount(1, $this->sent);
        $this->assertSame('239.0.0.1', $this->sent[0]['host']);
        $this->assertSame(9700, $this->sent[0]['port']);
        $this->assertSame('hello-digest', $this->sent[0]['payload']);
    }

    public function testSendToDeliversToSpecificPeer(): void
    {
        $this->transport->sendTo('10.0.0.9:9700', 'peer-digest');

        $this->assertCount(1, $this->sent);
        $this->assertSame('10.0.0.9', $this->sent[0]['host']);
        $this->assertSame(9700, $this->sent[0]['port']);
    }

    public function testHandlePacketMakesReceiveReturnIt(): void
    {
        $this->transport->handlePacket('inbound-digest', ['address' => '10.0.0.2', 'port' => 9700]);

        $got = $this->transport->receive(0.5);
        $this->assertSame('inbound-digest', $got);
    }
}
