<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Multicast;

use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;

class MulticastProcess extends Process
{

    /**
     * Initialize the multicast worker process.
     */
    public function init()
    {
        // TODO: Implement init() method.
    }

    /**
     * Called when the multicast worker process starts.
     */
    public function onProcessStart()
    {
        // TODO: Implement onProcessStart() method.
    }

    /**
     * Called when the multicast worker process stops.
     */
    public function onProcessStop()
    {
        // TODO: Implement onProcessStop() method.
    }

    /**
     * Handle a pipe message forwarded from another process.
     *
     * @param Message $message The incoming pipe message
     * @param Process $fromProcess The process that sent the message
     */
    public function onPipeMessage(Message $message, Process $fromProcess)
    {
        // TODO: Implement onPipeMessage() method.
    }
}