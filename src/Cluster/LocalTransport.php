<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

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
    public function start(): void
    {
        // No socket to bind for single-machine deployment.
    }

    public function tell(Location $location, string $method, array $arguments, ?string $traceId): bool
    {
        // Local placement is delivered via in-process IPC (ActorIpcProxy +
        // IpcProxy), not this transport. isRemote() is always false locally.
        return true;
    }

    public function ask(Location $location, string $method, array $arguments, ?string $traceId, float $timeOut)
    {
        // Local placement is delivered via in-process IPC; never reached when
        // the target actor is on the same node.
        return null;
    }

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
