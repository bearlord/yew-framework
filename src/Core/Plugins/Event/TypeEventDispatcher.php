<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

class TypeEventDispatcher extends AbstractEventDispatcher
{
    const type = "TypeEventDispatcher";

    /**
     * Handle event and Processing sent messages
     * @param Event $event
     */
    public function handleEventFrom(Event $event)
    {
        //No need to deal with EventFrom of type
    }

    /**
     * Dispatch event
     *
     * @param Event $event
     * @return bool
     */
    public function dispatchEvent(Event $event): bool
    {
        foreach ($event->getDstInfo($this->getName()) as $type) {
            $calls = $this->eventDispatcherManager->getEventCalls($type);
            if ($calls == null) continue;
            foreach ($calls as $call) {
                goWithContext(function () use ($call, $event) {
                    $call->send($event);
                });
            }
        }
        return true;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return self::type;
    }

}