<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

/**
 * Pluggable gossip wire layer. Decouples the membership algorithm
 * ({@see GossipClusterState}) from the actual socket. A {@see UdpGossipTransport}
 * broadcasts over UDP; tests inject an in-memory implementation.
 */
interface GossipTransport
{
    /**
     * Broadcast a gossip digest to the cluster (fire-and-forget).
     *
     * @param string $payload Serialised gossip digest payload
     * @return void
     */
    public function broadcast(string $payload): void;

    /**
     * Send a gossip digest to one specific peer (used for push/pull to a
     * randomly chosen node).
     *
     * @param string $peer "host:port" of the target seed/peer
     * @param string $payload Serialised gossip digest payload
     * @return void
     */
    public function sendTo(string $peer, string $payload): void;

    /**
     * Block until the next inbound gossip payload arrives (or timeout).
     *
     * @param float $timeout Seconds
     * @return string|null Raw payload, or null on timeout
     */
    public function receive(float $timeout): ?string;
}
