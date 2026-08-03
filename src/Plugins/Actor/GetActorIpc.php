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
     * Get the IPC proxy for calling the specified Actor.
     *
     * @param string $actorName
     * @param bool $oneWay
     * @param float $timeOut
     * @return ActorIpcProxy
     */
    public function callActor(string $actorName, bool $oneWay = false, float $timeOut = 5): ActorIpcProxy
    {
        return new ActorIpcProxy($actorName, $oneWay, $timeOut);
    }

    /**
     * Block until the specified Actor has been created.
     *
     * @param string $actorName
     * @param float $timeOut
     * @return void
     * @throws ActorException
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
