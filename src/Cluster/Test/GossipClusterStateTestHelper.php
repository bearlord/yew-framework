<?php

namespace Yew\Cluster\Test;

use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\Transport\GossipTransport;

/**
 * Reflection-based accessors for GossipClusterState's private members, so unit
 * tests can inject a transport without the class exposing public *ForTest() methods.
 */
class GossipClusterStateTestHelper
{
    public static function setTransport(GossipClusterState $state, GossipTransport $transport): void
    {
        $prop = new \ReflectionProperty($state, 'transport');
        $prop->setAccessible(true);
        $prop->setValue($state, $transport);
    }
}
