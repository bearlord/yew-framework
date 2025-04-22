<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

use Yew\Core\Order\Order;


abstract class AbstractEventDispatcher extends Order
{
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcherManager;

    /**
     * AbstractEventDispatcher constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->eventDispatcherManager = DIGet(EventDispatcher::class);
    }

    /**
     * Handle event
     *
     * @param Event $event
     * @return mixed
     */
    abstract public function handleEventFrom(Event $event);

    /**
     * Dispatch event
     *
     * @param Event $event
     * @return mixed
     */
    abstract public function dispatchEvent(Event $event): bool;
}