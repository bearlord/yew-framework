<?php

namespace Yew\Plugins\Actor\test;

use Yew\Cluster\Transport\GossipTransport;

/**
 * In-memory GossipTransport used by offline tests.
 *
 * It does not bind any socket. Messages handed to broadcast()/sendTo() are
 * delivered to a registered peer transport via a shared mesh, so two
 * FakeGossipTransport instances can simulate a real gossip exchange without
 * Swoole or UDP.
 */
class FakeGossipTransport implements GossipTransport
{
    /** @var array<string, FakeGossipTransport> peer name => transport */
    private static array $mesh = [];

    /** @var callable|null */
    private $onPacket;

    /** @var array<int, string> received raw payloads */
    private array $received = [];

    public static function resetMesh(): void
    {
        self::$mesh = [];
    }

    public function register(string $name, FakeGossipTransport $transport): void
    {
        self::$mesh[$name] = $transport;
    }

    public function setOnPacket(callable $cb): void
    {
        $this->onPacket = $cb;
    }

    public function broadcast(string $payload): void
    {
        foreach (self::$mesh as $name => $peer) {
            if ($peer === $this) {
                continue;
            }
            $peer->deliver($payload);
        }
    }

    public function sendTo(string $peer, string $payload): void
    {
        if (isset(self::$mesh[$peer])) {
            self::$mesh[$peer]->deliver($payload);
        }
    }

    private function deliver(string $payload): void
    {
        $this->received[] = $payload;
        if ($this->onPacket !== null) {
            ($this->onPacket)($payload);
        }
    }

    /** @return array<int, string> */
    public function drainReceived(): array
    {
        $out = $this->received;
        $this->received = [];
        return $out;
    }

    public function receive(float $timeout): ?string
    {
        return array_shift($this->received);
    }
}
