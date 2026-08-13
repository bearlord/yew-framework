<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Plugins\Ipc\IpcCallData;

/**
 * IPC call payload for Actor proxies.
 *
 * Unlike the plain IpcCallData, this carries the actor name as a dedicated
 * field so we never have to smuggle it through the class name (e.g.
 * "ClassName:actorName"). The actor instance lives inside ActorManager
 * (per-process), not the DI container, so the processor resolves it via
 * ActorManager::getActor($actorName) using this field.
 */
class ActorIpcCallData extends IpcCallData
{
    /**
     * @var string
     */
    private string $actorName;

    /**
     * @param string $className
     * @param string $actorName
     * @param string $name
     * @param array $arguments
     * @param bool $oneway
     */
    public function __construct(string $className, string $actorName, string $name, array $arguments, bool $oneway)
    {
        parent::__construct($className, $name, $arguments, $oneway);
        $this->actorName = $actorName;
    }

    /**
     * @return string
     */
    public function getActorName(): string
    {
        return $this->actorName;
    }
}
