<?php
/**
 * ESD framework
 * @author tmtbe <896369042@qq.com>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Queue;

use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;

class QueueProcess extends Process
{

    /**
     * @inheritDoc
     * @return mixed|void
     */
    public function init()
    {

    }

    /**
     * @inheritDoc
     * @return mixed|void
     */
    public function onProcessStart()
    {

    }

    /**
     * @inheritDoc
     * @return mixed|void
     */
    public function onProcessStop()
    {

    }

    /**
     * @inheritDoc
     * @param Message $message
     * @param Process $fromProcess
     * @return mixed|void
     */
    public function onPipeMessage(Message $message, Process $fromProcess)
    {

    }
}