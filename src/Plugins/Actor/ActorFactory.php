<?php

namespace Yew\Plugins\Actor;

use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Event\ActorCreateEvent;

class ActorFactory
{
    /**
     * Create
     * @param string $actionClass
     * @param string $actorName
     * @param null $data
     * @param bool $waitCreate
     * @param float|null $timeOut
     * @return \Yew\Plugins\Actor\ActorIpcProxy|false|void
     * @throws \Yew\Plugins\Actor\ActorException
     */
    public static function create(string $actionClass, string $actorName, $data = null, ?bool $waitCreate = true, ?float $timeOut = 5)
    {
        if ($waitCreate && ActorManager::getInstance()->hasActor($actorName)) {
            return new ActorIpcProxy($actorName, false, $timeOut);
        }

        $processes = Server::$instance->getProcessManager()->getProcessGroup(ActorConfig::GROUP_NAME);

        $nowProcess = ActorManager::getInstance()->getAtomic()->add();
        $index = $nowProcess % count($processes->getProcesses());

        Server::$instance->getEventDispatcher()->dispatchProcessEvent(new ActorCreateEvent(
            ActorCreateEvent::ActorCreateEvent,
            [
                $actionClass, $actorName, $data, true
            ]), $processes->getProcesses()[$index]);

        if ($waitCreate) {
            $call = Server::$instance->getEventDispatcher()->listen(ActorCreateEvent::ActorCreateReadyEvent . ":" . $actorName, null, true);
            $result = $call->wait($timeOut);
            if ($result == null) {
                return false;
            }

            return new ActorIpcProxy($actorName, false, $timeOut);
        }
    }
}