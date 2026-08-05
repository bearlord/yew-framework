<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Cluster\State\Location;
use Yew\Plugins\Actor\Telemetry\Tracer;
use Yew\Plugins\Ipc\IpcProxy;
use Yew\Plugins\Ipc\IpcCallMessage;
use Yew\Coroutine\Server\Server;

class ActorIpcProxy extends IpcProxy
{
    /**
     * @var Location|null Resolved physical location (location transparency seam)
     */
    protected ?Location $location = null;

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
        $manager = ActorManager::getInstance();

        // Location-transparent resolution: ask the shard router where the actor
        // lives. In single-machine mode this returns the local process; a
        // clustered router would return a remote node, and delivery would go
        // through the remote transport instead of in-process IPC.
        $location = $manager->getShardRouter()->locate($actorName);
        if ($location === null) {
            throw new ActorException(sprintf("Actor '%s' location not found, cannot build proxy", $actorName));
        }

        $actorInfo = $manager->getActorInfo($actorName);
        if ($actorInfo == null) {
            throw new ActorException(sprintf("Actor '%s' info not found, cannot build proxy", $actorName));
        }

        // Local actors are delivered via in-process IPC. Remote actors are
        // delivered through the cluster remote transport (TCP remoting).
        if ($location->isLocal()) {
            parent::__construct($actorInfo->getProcess(), $actorInfo->getClassName() . ":" . $actorInfo->getName(), $oneWay, $timeOut);
            return;
        }

        // Location-transparent remote delivery seam.
        $location->setActorName($actorName);
        $this->location = $location;
        $this->remote = $manager->getRemoteTransport();
    }

    /**
     * @var \Yew\Cluster\Transport\RemoteTransport|null Transport used when the
     *      target actor lives on another cluster node.
     */
    protected $remote = null;

    /**
     * Fire-and-forget: invoke a method on the actor without waiting for a reply.
     *
     * @param string $method
     * @param array  $arguments
     * @return bool
     */
    public function tell(string $method, array $arguments = []): bool
    {
        $arguments['__traceId'] = Tracer::currentTraceId();
        if ($this->isRemote()) {
            return $this->remote->tell($this->location, $method, $arguments, Tracer::currentTraceId());
        }
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
        $arguments['__traceId'] = Tracer::currentTraceId();
        if ($this->isRemote()) {
            return $this->remote->ask($this->location, $method, $arguments, Tracer::currentTraceId(), $timeOut > 0 ? $timeOut : $this->timeOut);
        }
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
            $arguments['__traceId'] = Tracer::currentTraceId();
            if ($this->isRemote()) {
                try {
                    $result = $this->remote->ask($this->location, $method, $arguments, Tracer::currentTraceId(), $timeOut > 0 ? $timeOut : $this->timeOut);
                    $future->resolve($result);
                } catch (\Throwable $e) {
                    $future->reject($e);
                }
                return;
            }
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
     * True when this proxy targets a remote cluster node.
     */
    private function isRemote(): bool
    {
        return $this->remote !== null && $this->location !== null && !$this->location->isLocal();
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
