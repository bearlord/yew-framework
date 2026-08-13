<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Message\Message;
use Yew\Plugins\Ipc\IpcMessageProcessor;

/**
 * Dedicated IPC message for Actor proxies.
 *
 * The actor name travels in its own field (ActorIpcCallData::$actorName)
 * instead of being concatenated onto the class name, keeping the class name
 * a valid DI identifier and letting the processor route to ActorManager
 * without string parsing.
 */
class ActorIpcCallMessage extends Message
{
    /**
     * @param string $className
     * @param string $actorName
     * @param string $name
     * @param array $arguments
     * @param bool $oneway
     */
    public function __construct(string $className, string $actorName, string $name, array $arguments, bool $oneway)
    {
        parent::__construct(IpcMessageProcessor::TYPE, new ActorIpcCallData($className, $actorName, $name, $arguments, $oneway));
    }

    /**
     * @return ActorIpcCallData
     */
    public function getProcessIpcCallData(): ActorIpcCallData
    {
        return $this->getData();
    }
}
