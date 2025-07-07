<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Event\ActorCreateEvent;

trait GetActorRpc
{
    /**
     * @param string $actorName
     * @param bool $oneway
     * @param float $timeOut
     * @return ActorRPCProxy
     * @throws Exception\ActorException
     */
    public function callActor(string $actorName, bool $oneway = false, float $timeOut = 5): ActorRPCProxy
    {
        return new ActorRPCProxy($actorName, $oneway, $timeOut);
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
