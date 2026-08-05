<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

use Yew\Plugins\Actor\ActorMessage;

/**
 * Network transport for cross-node actor messaging (Akka remoting / Orleans
 * silo-to-silo equivalent).
 *
 * The local deployment uses no transport (messages go through in-process IPC),
 * so {@see LocalTransport} is a no-op stub. A clustered deployment would
 * implement this over TCP/QUIC+gossip, serialising {@see ActorMessage} to a
 * remote node resolved by the {@see ShardRouter}.
 */
interface RemoteTransport
{
    /**
     * Start listening for inbound actor messages (server side). No-op for
     * transports that do not bind a socket (e.g. in-process).
     */
    public function start(): void;

    /**
     * Fire-and-forget delivery to a remote actor.
     *
     * @param Location $location Target actor location (remote node)
     * @param string   $method   Actor method to invoke
     * @param array    $arguments Method arguments
     * @param string   $traceId  Current trace id for cross-node propagation
     * @return bool True if the envelope was dispatched
     */
    public function tell(Location $location, string $method, array $arguments, ?string $traceId): bool;

    /**
     * Request-response delivery to a remote actor. Blocks until a reply with the
     * same msgId arrives (or the timeout elapses).
     *
     * @param Location $location Target actor location (remote node)
     * @param string   $method   Actor method to invoke
     * @param array    $arguments Method arguments
     * @param string   $traceId  Current trace id for cross-node propagation
     * @param float    $timeOut  Seconds to wait for the reply
     * @return mixed The remote actor's return value, or null on timeout
     */
    public function ask(Location $location, string $method, array $arguments, ?string $traceId, float $timeOut);

    /**
     * Whether this transport can reach the given (remote) location.
     */
    public function supports(Location $location): bool;
}
