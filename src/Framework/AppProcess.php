<?php

namespace Yew\Framework;

use Exception;
use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;

class AppProcess extends Process
{
    /**
     * @return void
     * @throws Exception
     */
    public function init()
    {
        $this->log = Server::$instance->getLog();
    }

    /**
     *
     * @return void
     */
    public function onProcessStart()
    {
        $this->log->debug('Process start');
    }

    /**
     * @inheritDoc
     * @return void
     */
    public function onProcessStop()
    {
        $code = swoole_errno();
        $msg = swoole_strerror($code);

        $this->log->debug('Process stop' . " Error Code: $code, Error Message: $msg");
    }

    /**
     *
     * @param Message $message
     * @param Process $fromProcess
     * @return void
     */
    public function onPipeMessage(Message $message, Process $fromProcess)
    {
    }
}