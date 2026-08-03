<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

use Yew\Plugins\Actor\ActorMessage;

/**
 * No-op transport for the single-machine deployment.
 *
 * All actors live on the local node, so message delivery is handled by the
 * existing in-process IPC layer rather than a network transport. This class
 * exists purely as the default {@see RemoteTransport} implementation behind
 * the location-transparent addressing abstraction.
 */
class LocalTransport implements RemoteTransport
{
    public function send(Location $location, ActorMessage $message)
    {
        // Local placement is delivered via in-process IPC (ActorIpcProxy +
        // IpcProxy), not this transport.
        return null;
    }

    public function supports(Location $location): bool
    {
        return $location->isLocal();
    }
}
