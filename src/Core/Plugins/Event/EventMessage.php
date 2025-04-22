<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

use Yew\Core\Message\Message;

/**
 * Class Event
 * @package Yew\BaseServer\Plugins\Event
 */
class EventMessage extends Message
{
    const type = "@event";

    /**
     * EventMessage constructor.
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        parent::__construct(self::type, $event);
    }

    /**
     * Get event
     *
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->getData();
    }
}