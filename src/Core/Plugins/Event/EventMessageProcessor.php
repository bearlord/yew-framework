<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

use Yew\Core\Message\Message;
use Yew\Core\Message\MessageProcessor;

/**
 * Class EventMessageProcessor
 * @package Yew\BaseServer\Plugins\Event
 */
class EventMessageProcessor extends MessageProcessor
{
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * EventMessageProcessor constructor.
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(EventDispatcher $eventDispatcher)
    {
        parent::__construct(EventMessage::type);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @inheritDoc
     * @param Message $message
     * @return mixed
     */
    public function handler(Message $message): bool
    {
        if ($message instanceof EventMessage) {
            $this->eventDispatcher->dispatchEvent($message->getEvent());
            return true;
        }
        return false;
    }
}