<?php
/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Amqp;

use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;

class AmqpProcess extends Process
{

    /**
     * @return mixed
     */
    public function init()
    {

    }

    /**
     * @return mixed|void
     */
    public function onProcessStart()
    {

    }

    /**
     * @return mixed|void
     */
    public function onProcessStop()
    {

    }

    /**
     * @param Message $message
     * @param Process $fromProcess
     * @return mixed|void
     */
    public function onPipeMessage(Message $message, Process $fromProcess)
    {

    }
}