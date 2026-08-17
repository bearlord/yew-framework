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
use Yew\Plugins\Ipc\IpcException;
use Yew\Plugins\Ipc\IpcManager;
use Yew\Plugins\Ipc\IpcResultData;
use Yew\Plugins\Actor\ActorIpcCallMessage;
use Yew\Coroutine\Server\Server;

class ActorIpcProxy extends IpcProxy
{
    /**
     * @var Location|null Resolved physical location (location transparency seam)
     */
    protected ?Location $location = null;

    /**
     * @var string|null Actor name, carried separately from the class name so the
     *      IPC processor can resolve the per-process ActorManager instance.
     */
    protected ?string $actorName = null;

    /**
     * @var \Yew\Cluster\Transport\RemoteTransport|null Transport used when the
     *      target actor lives on another cluster node.
     */
    protected $remote = null;

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
    public function __construct(string $actorName, bool $oneWay, float $timeOut = 5)
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

        // Local actors need a resolved ActorInfo (process + class). Remote actors
        // are addressed purely by the shard-router Location and have no local
        // ActorInfo object, so the null check only applies to local placement.
        if ($location->isLocal()) {
            $actorInfo = $manager->getActorInfo($actorName);
            if ($actorInfo == null) {
                throw new ActorException(sprintf("Actor '%s' info not found, cannot build proxy", $actorName));
            }
            // Keep the class name clean and carry the actor name in its own
            // field via the dedicated ActorIpcCallMessage type.
            parent::__construct($actorInfo->getProcess(), $actorInfo->getClassName(), $oneWay, $timeOut);
            $this->actorName = $actorInfo->getName();
            return;
        }

        // Location-transparent remote delivery seam.
        $location->setActorName($actorName);
        $this->location = $location;
        $this->remote = $manager->getRemoteTransport();
        $this->timeOut = $timeOut;
    }

    /**
     * Static factory: build an ActorIpcProxy, returning false instead of throwing
     * when the actor's location or info cannot be resolved. This is the unified
     * entry point that replaces the previous Actor::getProxy() helper.
     *
     * @param string $actorName
     * @param bool $oneWay
     * @param float $timeOut
     * @return self|false
     */
    public static function create(string $actorName, bool $oneWay, float $timeOut = 0)
    {
        try {
            return new self($actorName, $oneWay, $timeOut);
        } catch (ActorException $exception) {
            return false;
        }
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
    public function isRemote(): bool
    {
        return $this->remote !== null && $this->location !== null && !$this->location->isLocal();
    }

    /**
     * @param ActorMessage $message
     * @return bool
     */
    public function sendMessage(ActorMessage $message): bool
    {
        $message = new ActorIpcCallMessage($this->className, $this->actorName, "sendMessage", [$message], true);

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

        $message = new ActorIpcCallMessage($actorInfo->getClassName(), $actorName, "sendMessage", [$message], true);
        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $actorInfo->getProcess());

        return true;
    }

    /**
     * Route a method call to the target actor via the dedicated ActorIpcCallMessage
     * type, which carries the actor name in its own field instead of polluting
     * the class name. Remote actors are dispatched through the remote transport.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed|void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function __call(string $name, array $arguments)
    {
        // Lifecycle methods must never be forwarded over IPC: tearing down the
        // actor (destroy/stop) before the reply is sent would hang the caller on
        // a silent timeout, and removeActor/unregister mutate registry state
        // that the caller cannot safely drive remotely. Route these through
        // ActorSystem::destroy() instead.
        static $reserved = [
            'destroy'      => true,
            'stop'         => true,
            'removeActor'  => true,
            'unregister'   => true,
            'shutdown'     => true,
        ];
        if (isset($reserved[$name])) {
            throw new \BadMethodCallException(sprintf(
                "ActorIpcProxy: method '%s' is a reserved lifecycle method and must not be called over IPC; use ActorSystem::destroy() instead",
                $name
            ));
        }

        // Remote actors are dispatched through the cluster transport, NOT the
        // inherited local-IPC __call (which would send a IpcCallMessage to an
        // unresolved $this->process and silently time out). Route through ask(),
        // which uses $this->remote->ask() over the remote transport.
        if ($this->isRemote()) {
            return $this->ask($name, $arguments);
        }

        if ($this->sessionId != null) {
            $arguments["sessionId"] = $this->sessionId;
        }

        $message = new ActorIpcCallMessage(
            $this->className,
            $this->actorName,
            $name,
            $arguments,
            $this->oneway
        );

        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $this->process);

        if (!$this->oneway) {
            $channel = IpcManager::getChannel($message->getProcessIpcCallData()->getToken());
            $result = $channel->pop($this->timeOut);
            $channel->close();

            if ($result instanceof IpcResultData) {
                if ($result->getErrorClass() != null) {
                    throw new IpcException("[{$result->getErrorClass()}]{$result->getErrorMessage()}", $result->getErrorCode());
                } else {
                    return $result->getResult();
                }
            } else {
                throw new IpcException("Time out");
            }
        }
    }
}
