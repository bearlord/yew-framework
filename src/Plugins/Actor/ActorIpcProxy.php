<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Plugins\Ipc\IpcProxy;
use Yew\Plugins\Ipc\IpcCallMessage;
use Yew\Coroutine\Server\Server;

class ActorIpcProxy extends IpcProxy
{
    /**
     * Proxy to a remote Actor. Method calls are routed to the actor process via IPC.
     *
     * Two messaging semantics are supported (Akka-style):
     *  - tell(): fire-and-forget, no reply expected (one-way IPC)
     *  - ask():  request-response, blocks the current coroutine until the actor replies
     *  - askFuture(): request-response without blocking immediately (returns an ActorFuture)
     *
     * Any public method of the target Actor can also be invoked directly via the
     * magic __call (inherited), which behaves as a non-oneway ask by default.
     *
     * @param string $actorName
     * @param bool $oneWay
     * @param float $timeOut
     * @throws ActorException
     */
    public function __construct(string $actorName, bool $oneWay, float $timeOut = 0)
    {
        $actorInfo = ActorManager::getInstance()->getActorInfo($actorName);
        if ($actorInfo == null) {
            return;
        }

        parent::__construct($actorInfo->getProcess(), $actorInfo->getClassName() . ":" . $actorInfo->getName(), $oneWay, $timeOut);
    }

    /**
     * Fire-and-forget: invoke a method on the actor without waiting for a reply.
     *
     * @param string $method
     * @param array  $arguments
     * @return bool
     */
    public function tell(string $method, array $arguments = []): bool
    {
        $savedOneWay = $this->oneway;
        $this->oneway = true;
        try {
            $this->__call($method, $arguments);
            return true;
        } finally {
            $this->oneway = $savedOneWay;
        }
    }

    /**
     * Request-response: invoke a method and block until the actor replies.
     *
     * @param string $method
     * @param array  $arguments
     * @param float  $timeOut Override the proxy timeout for this call
     * @return mixed The actor's return value
     */
    public function ask(string $method, array $arguments = [], float $timeOut = 0)
    {
        $savedOneWay = $this->oneway;
        $savedTimeOut = $this->timeOut;
        $this->oneway = false;
        if ($timeOut > 0) {
            $this->timeOut = $timeOut;
        }
        try {
            return $this->__call($method, $arguments);
        } finally {
            $this->oneway = $savedOneWay;
            $this->timeOut = $savedTimeOut;
        }
    }

    /**
     * Request-response without blocking immediately: returns a future that can be awaited later.
     *
     * @param string $method
     * @param array  $arguments
     * @param float  $timeOut Override the proxy timeout for this call
     * @return ActorFuture
     */
    public function askFuture(string $method, array $arguments = [], float $timeOut = 0): ActorFuture
    {
        $future = new ActorFuture();
        $savedOneWay = $this->oneway;
        $savedTimeOut = $this->timeOut;

        goWithContext(function () use ($method, $arguments, $timeOut, $future, $savedOneWay, $savedTimeOut) {
            $this->oneway = false;
            if ($timeOut > 0) {
                $this->timeOut = $timeOut;
            }
            try {
                $result = $this->__call($method, $arguments);
                $future->resolve($result);
            } catch (\Throwable $e) {
                $future->reject($e);
            } finally {
                $this->oneway = $savedOneWay;
                $this->timeOut = $savedTimeOut;
            }
        });

        return $future;
    }

    /**
     * @param ActorMessage $message
     * @return bool
     */
    public function sendMessage(ActorMessage $message): bool
    {
        $message = new ProcessIpcCallMessage($this->className, "sendMessage", [$message], true);
        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $this->process);

        return true;
    }

    /**
     * @param ActorMessage $message
     * @param string $actorName
     * @return bool
     * @throws \Exception
     */
    public function sendMessageToActor(ActorMessage $message, string $actorName): bool
    {
        $actorInfo = ActorManager::getInstance()->getActorInfo($actorName);
        if ($actorInfo == null) {
            return false;
        }

        $message = new IpcCallMessage($actorInfo->getClassName(), "sendMessage", [$message], true);
        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $actorInfo->getProcess());

        return true;
    }
}
