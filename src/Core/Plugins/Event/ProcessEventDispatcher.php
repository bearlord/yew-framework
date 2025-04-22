<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

use Yew\Core\Server\Server;

class ProcessEventDispatcher extends AbstractEventDispatcher
{
    const type = "ProcessEventDispatcher";

    /**
     * ProcessEventDispatcher constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->atBefore(TypeEventDispatcher::class);
    }

    /**
     * @inheritDoc
     * @param Event $event
     */
    public function handleEventFrom(Event $event)
    {
        if (Server::$instance == null || Server::$instance->getProcessManager() == null) {
            $event->setSourceInfo($this->getName(), -1);
        } else {
            $event->setSourceInfo($this->getName(), Server::$instance->getProcessManager()->getCurrentProcessId());
        }
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return self::type;
    }

    /**
     * @inheritDoc
     * @param Event $event
     * @return bool
     */
    public function dispatchEvent(Event $event): bool
    {
        $toProcess = $event->getDstInfo($this->getName());
        if ($toProcess == null) {
            return true;
        } else {
            $next = false;
            foreach ($toProcess as $processId) {
                $process = Server::$instance->getProcessManager()->getProcessFromId($processId);
                if ($processId == Server::$instance->getProcessManager()->getCurrentProcessId()) {
                    $next = true;
                } else {
                    Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage(new EventMessage($event), $process);
                }
            }
            //Handle the process, if there is no process, you can not continue to pass processing
            return $next;
        }
    }
}