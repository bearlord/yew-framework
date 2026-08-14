<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;

/**
 * Helper process that owns the authoritative {@see ClusterState}.
 *
 * It is intentionally a thin shell: all cluster logic lives in ClusterState,
 * which is registered into the DI container of THIS process (see
 * ClusterStatePlugin). Worker processes talk to it through the GetClusterState
 * IPC proxy, exactly like the Connection pattern.
 */
class ClusterStateProcess extends Process
{
    public function init()
    {
    }

    public function onProcessStart()
    {
    }

    public function onProcessStop()
    {
    }

    public function onPipeMessage(Message $message, Process $fromProcess)
    {
    }
}
