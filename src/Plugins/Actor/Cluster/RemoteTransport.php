<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

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
     * Send a message to an actor located on a (possibly remote) node.
     *
     * @param Location     $location Target actor location
     * @param ActorMessage $message  Message to deliver
     * @return mixed Reply payload for ask-style calls, null for tell
     */
    public function send(Location $location, ActorMessage $message);

    /**
     * Whether this transport can reach the given location.
     */
    public function supports(Location $location): bool;
}
