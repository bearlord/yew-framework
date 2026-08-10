<?php

namespace Yew\Cluster\State\Test;

use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\State\GossipMessage;
use Yew\Cluster\Transport\GossipTransport;

/**
 * Reflection-based accessors for GossipClusterState's private members.
 *
 * The production class keeps transport / signing / fragmentation internals
 * private on purpose. These helpers let unit tests reach them without the
 * class having to expose *ForTest() public methods.
 */
class GossipClusterStateTestHelper
{
    public static function setTransport(GossipClusterState $state, GossipTransport $transport): void
    {
        $prop = new \ReflectionProperty($state, 'transport');
        $prop->setAccessible(true);
        $prop->setValue($state, $transport);
    }

    public static function sign(GossipMessage $msg, int $now): string
    {
        $m = new \ReflectionMethod(GossipClusterState::class, 'sign');
        $m->setAccessible(true);
        $m->invoke(new GossipClusterState('helper'), $msg, $now);
        return (string) $msg->sig;
    }

    public static function verify(GossipMessage $msg, int $now): bool
    {
        $m = new \ReflectionMethod(GossipClusterState::class, 'verify');
        $m->setAccessible(true);
        return (bool) $m->invoke(new GossipClusterState('helper'), $msg, $now);
    }

    public static function ingest(string $payload, int $now): ?string
    {
        $m = new \ReflectionMethod(GossipClusterState::class, 'ingest');
        $m->setAccessible(true);
        return $m->invoke(new GossipClusterState('helper'), $payload, $now);
    }

    public static function sendReliable(string $peer, GossipMessage $msg, bool $track = true): void
    {
        $m = new \ReflectionMethod(GossipClusterState::class, 'sendReliable');
        $m->setAccessible(true);
        $m->invoke(new GossipClusterState('helper'), $peer, $msg, $track);
    }

    public static function fragAcked(GossipClusterState $state, string $of, int $seq): bool
    {
        $prop = new \ReflectionProperty($state, 'sentFrags');
        $prop->setAccessible(true);
        $sent = $prop->getValue($state);
        return isset($sent[$of]['acked'][$seq]) && $sent[$of]['acked'][$seq] === true;
    }

    public static function dispatch(GossipClusterState $state, GossipMessage $msg): void
    {
        $m = new \ReflectionMethod($state, 'dispatch');
        $m->setAccessible(true);
        $m->invoke($state, $msg);
    }

    public static function keyId(GossipClusterState $state): ?string
    {
        $prop = new \ReflectionProperty($state, 'key');
        $prop->setAccessible(true);
        $key = $prop->getValue($state);
        return $key === null ? null : $key->getKeyId();
    }

    /**
     * @return array<string,array{peer:string,payload:string,retries:int,nextAt:int}>
     */
    public static function pendingOut(GossipClusterState $state): array
    {
        $prop = new \ReflectionProperty($state, 'pendingOut');
        $prop->setAccessible(true);
        return $prop->getValue($state);
    }
}
