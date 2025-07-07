<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Event\ActorCreateEvent;

trait GetActorIpc
{
    /**
     * @param string $actorName
     * @param bool $oneway
     * @param float $timeOut
     * @return ActorIpcProxy
     * @throws Exception\ActorException
     */
    /**
     * @param string $actorName
     * @param bool $oneway
     * @param float $timeOut
     * @return ActorIpcProxy
     */
    public function callActor(string $actorName, bool $oneway = false, float $timeOut = 5): ActorIpcProxy
    {
        return new ActorIpcProxy($actorName, $oneway, $timeOut);
    }

    /**
     * @param string $actorName
     * @param float $timeOut
     * @return void
     * @throws \Exception
     */
    public function waitActorCreate(string $actorName, float $timeOut = 5)
    {
        if (!ActorManager::getInstance()->hasActor($actorName)) {
            $call = Server::$instance->getEventDispatcher()->listen(ActorCreateEvent::ActorCreateReadyEvent . ":" . $actorName, null, true);
            $result = $call->wait($timeOut);
            if ($result == null) {
                throw new ActorException("wait actor create timeout");
            }
        }
    }
}
