<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Process;

use Exception;
use Yew\Core\Message\Message;
use Yew\Core\Server\Server;

/**
 * Class ManagerProcess
 * @package Yew\Core\Server\Process
 */
class ManagerProcess extends Process
{
    const NAME = "manager";

    const ID = "-2";

    /**
     * ManagerProcess constructor.
     * @param Server $server
     * @throws Exception
     */
    public function __construct(Server $server)
    {
        parent::__construct($server, self::ID, self::NAME, Process::SERVER_GROUP);
    }

    /**
     * @inheritDoc
     * @return void
     */
    public function onProcessStart()
    {
        Process::setProcessTitle(Server::$instance->getServerConfig()->getName() . "-" . $this->getProcessName());
        $this->processPid = getmypid();
        $this->server->getProcessManager()->setCurrentProcessId($this->processId);
    }

    /**
     * @inheritDoc
     */
    public function onProcessStop()
    {
    }

    /**
     * @inheritDoc
     *
     * @param Message $message
     * @param Process $fromProcess
     */
    public function onPipeMessage(Message $message, Process $fromProcess)
    {
    }


    /**
     * @inheritDoc
     *
     */
    public function init()
    {
    }
}
