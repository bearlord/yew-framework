<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Plugins\Ipc\IpcProxy;
use Yew\Plugins\Ipc\IpcCallMessage;
use Yew\Coroutine\Server\Server;

class ActorIpcProxy extends IpcProxy
{
    /**
     * @param string $actorName
     * @param bool $oneway
     * @param float $timeOut
     * @throws ActorException
     */
    public function __construct(string $actorName, bool $oneway, float $timeOut = 0)
    {
        $actorInfo = ActorManager::getInstance()->getActorInfo($actorName);
        if ($actorInfo == null) {
            return;
        }

        parent::__construct($actorInfo->getProcess(), $actorInfo->getClassName() . ":" . $actorInfo->getName(), $oneway, $timeOut);
    }

    /**
     * @param ActorMessage $message
     * @return bool
     */
    public function sendMessage(ActorMessage $message): bool
    {
        $message = new ProcessIpcCallMessage($this->className, "sendMessage", [$message], true);
        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $this->process);

        return true;
    }

    /**
     * @param ActorMessage $message
     * @param string $actorName
     * @return bool
     * @throws \Exception
     */
    public function sendMessageToActor(ActorMessage $message, string $actorName): bool
    {
        $actorInfo = ActorManager::getInstance()->getActorInfo($actorName);
        if ($actorInfo == null) {
            return false;
        }

        $message = new IpcCallMessage($actorInfo->getClassName(), "sendMessage", [$message], true);
        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $actorInfo->getProcess());

        return true;
    }
}
