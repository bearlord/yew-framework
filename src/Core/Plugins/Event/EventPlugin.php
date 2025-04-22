<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

use Yew\Core\Channel\Channel;
use Yew\Core\Context\Context;
use Yew\Core\Exception\Exception;
use Yew\Core\Message\Message;
use Yew\Core\Message\MessageProcessor;
use Yew\Core\Plugin\AbstractPlugin;

class EventPlugin extends AbstractPlugin
{
    /**
     * @var EventDispatcher
     */
    private EventDispatcher $eventDispatcher;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function init(Context $context)
    {
        parent::init($context);

        //Create event dispatcher
        $this->eventDispatcher = DIGet(EventDispatcher::class);

        //Add eventDispatcher type
        $this->eventDispatcher->addOrder(new ProcessEventDispatcher());
        $this->eventDispatcher->addOrder(new TypeEventDispatcher());
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        class_exists(MessageProcessor::class);
        class_exists(EventMessageProcessor::class);
        DIGet(EventCall::class, [$this->eventDispatcher, ""]);
        DIGet(Channel::class);
        class_exists(Message::class);
        class_exists(EventMessage::class);
    }

    /**
     * @param Context $context
     * @return void
     * @throws Exception
     */
    public function beforeProcessStart(Context $context)
    {
        //Register event dispatch handler
        MessageProcessor::addMessageProcessor(new EventMessageProcessor($this->eventDispatcher));

        //Ready
        $this->ready();
    }

    /**
     * Get name
     * @return string
     */
    public function getName(): string
    {
        return "Event";
    }
}
