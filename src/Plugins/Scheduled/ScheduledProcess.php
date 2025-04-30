<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled;

use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;

class ScheduledProcess extends Process
{

    /**
     * @return void
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