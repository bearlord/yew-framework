<?php
/**
 * Yew framework - Connection plugin
 *
 * Helper process that owns the connection-level routing state. It runs the
 * Connection business object and answers IPC calls forwarded by GetConnection.
 */

namespace Yew\Plugins\Connection;

use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;

class ConnectionProcess extends Process
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
