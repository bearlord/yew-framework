<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use DI\Annotation\Inject;
use Yew\Core\Channel\Channel;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Actor\Event\ActorCreateEvent;
use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Plugins\Actor\Log\LogFactory;
use Yew\Plugins\Actor\Log\Logger;
use Yew\Plugins\Actor\Mailbox\BlockStrategy;
use Yew\Plugins\Actor\Mailbox\DropStrategy;
use Yew\Plugins\Actor\Mailbox\FailStrategy;
use Yew\Plugins\Actor\Mailbox\MailboxOverflowStrategy;
use Yew\Plugins\Actor\Multicast\Multicast;
use Yew\Plugins\Actor\Supervision\Directive;
use Yew\Plugins\Actor\Supervision\EscalateStrategy;
use Yew\Plugins\Actor\Supervision\ResumeStrategy;
use Yew\Plugins\Actor\Supervision\RestartStrategy;
use Yew\Plugins\Actor\Supervision\StopStrategy;
use Yew\Plugins\Actor\Supervision\SupervisorStrategy;
use Yew\Plugins\Actor\Persistence\ActorStore;
use Yew\Plugins\Actor\Persistence\ActorEvent;
use Yew\Plugins\Actor\Persistence\Snapshot;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Dispatcher\Dispatcher;
use Yew\Plugins\Actor\Dispatcher\CoroutineDispatcher;
use Yew\Plugins\Actor\Dispatcher\PinnedDispatcher;
use Yew\Plugins\Actor\Dispatcher\ThreadPoolDispatcher;
use Yew\Plugins\Actor\Message\Message;
use Yew\Plugins\Actor\Message\MessageType;
use Yew\Plugins\Actor\Telemetry\ActorTelemetry;
use Yew\Plugins\Actor\Telemetry\Tracer;
use Yew\Coroutine\Server\Server;
use Yew\Yew;
use Swoole\Timer;

abstract class Actor
{
    use GetLogger;

    /**
     * @var Multicast
     */
    protected Multicast $multicast;

    /**
     * @var MailboxOverflowStrategy Strategy applied when the mailbox is full
     */
    protected MailboxOverflowStrategy $mailboxOverflowStrategy;

    /**
     * @var SupervisorStrategy Strategy applied when the actor fails while handling a message
     */
    protected SupervisorStrategy $supervisorStrategy;

    /**
     * @var int Consecutive failure count, reset after a successful message
     */
    protected int $restartAttempts = 0;

    /**
     * @var ActorStore|null Persistence backend (null when persistence disabled)
     */
    protected ?ActorStore $store = null;

    /**
     * @Inject()
     * @var ClusterActorStore|null Cluster-aware store injected by the framework
     *      when cross-node durability is enabled. Takes precedence over the
     *      local-only FileActorStore in init().
     */
    protected ?ClusterActorStore $injectedStore = null;

    /**
     * @var int Monotonic event sequence for this actor (event sourcing)
     */
    protected int $eventSequence = 0;

    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @Inject()
     * @var EventDispatcher
     */
    protected EventDispatcher $eventDispatcher;

    /**
     * @Inject()
     * @var ActorConfig
     */
    protected ActorConfig $actorConfig;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var array data
     */
    protected array $data = [];

    /**
     * @var array timer ids
     */
    protected array $timerIds = [];

    /**
     * @var Logger
     */
    protected Logger $logHandle;

    /**
     * @var int Lifecycle state: 0=initial, 2=recovered/ready
     */
    protected int $state = 0;

    /**
     * @var string|null Parent actor name (supervision tree), null for root actors
     */
    protected ?string $parentName = null;

    /**
     * @var Dispatcher Execution model for this actor (coroutine / pinned / thread-pool)
     */
    protected Dispatcher $dispatcher;

    /**
     * @var array<string, callable> Typed message routing table (MessageType name => handler)
     */
    protected array $messageHandlers = [];

    /**
     * @param string      $name
     * @param bool        $isCreated
     * @param string|null $parentName Parent actor name for the supervision tree
     * @throws \DI\DependencyException
     */
    final public function __construct(string $name, bool $isCreated = false, ?string $parentName = null)
    {
        // Actors must be created only inside an actor process
        $processName = Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName();
        if (stripos($processName, 'actor') === false) {
            throw new ActorException(sprintf("Actor can only be created in an actor process, current process is '%s'", $processName));
        }

        $this->name = $name;
        $this->parentName = $parentName;

        Server::$instance->getContainer()->injectOn($this);
        if ($isCreated) {
            ActorManager::getInstance()->addActor($this, $parentName);
        }

        $this->init();

        $this->recovery();
    }

    /**
     * @return void
     */
    protected function init()
    {
        $this->iniChannel();

        $this->supervisorStrategy = $this->resolveSupervisorStrategy($this->actorConfig->getSupervisorStrategy());
        $this->dispatcher = $this->resolveDispatcher($this->actorConfig->getDispatcher());

        if ($this->actorConfig->isPersistenceEnabled()) {
            // Use the cluster-aware store when the framework injected one
            // (cross-node durability); otherwise fall back to local-only files.
            $this->store = $this->injectedStore
                ?? (new FileActorStore($this->actorConfig->getPersistenceDir()));
            // Bind this actor's name so the store can address/ replicate it.
            $this->store->setActorName($this->name)->init();
        }

        //Loop process the information in the mailbox. The dispatcher decides in
        //which execution context each message is actually handled.
        goWithContext(function () {
            while (true) {
                $mailboxDepth = $this->channel->length();
                $message = $this->channel->pop();
                if ($message === false) {
                    break;
                }
                $start = microtime(true);
                $this->dispatcher->dispatch($this, $message);
                ActorTelemetry::record($this->name, microtime(true) - $start, $mailboxDepth);
            }
        });

        $this->logHandle = LogFactory::create($this->name);

        $this->multicast = new Multicast($this->name, DIGet(\Yew\Plugins\Actor\Multicast\MulticastConfig::class));

        $saveContextTime = Server::$instance->getConfigContext()->get("actor.saveContextTime", 10);
        $this->tick($saveContextTime * 1000, [$this, "saveContext"]);
    }
    
    protected function iniChannel()
    {
        $this->channel = DIGet(Channel::class, [$this->actorConfig->getMailboxCapacity()]);
        $this->mailboxOverflowStrategy = $this->resolveOverflowStrategy($this->actorConfig->getMailboxOverflow());
    }

    /**
     * Resolve the configured overflow strategy by name.
     *
     * @param string $name One of "block", "drop", "fail"
     */
    protected function resolveOverflowStrategy(string $name): MailboxOverflowStrategy
    {
        switch (strtolower($name)) {
            case 'drop':
                return new DropStrategy();
            case 'fail':
                return new FailStrategy();
            case 'block':
            default:
                return new BlockStrategy();
        }
    }

    /**
     * Resolve the configured supervisor strategy by name.
     *
     * @param string $name One of "restart", "resume", "stop", "escalate"
     */
    protected function resolveSupervisorStrategy(string $name): SupervisorStrategy
    {
        switch (strtolower($name)) {
            case 'resume':
                return new ResumeStrategy();
            case 'stop':
                return new StopStrategy();
            case 'escalate':
                return new EscalateStrategy();
            case 'restart':
            default:
                return new RestartStrategy($this->actorConfig->getSupervisorMaxRetries());
        }
    }

    /**
     * Handle a mailbox message under supervision.
     *
     * Any exception thrown while handling the message is reported to the
     * supervisor strategy, which decides the recovery directive:
     *  - resume:   keep state, continue with the next message
     *  - restart:  rebuild volatile state via onRestart(), keep identity/data
     *  - stop:     terminate the actor
     *  - escalate: rethrow, terminating the mailbox loop
     *
     * @param ActorMessage|false $message
     */
    protected function processWithSupervision($message): void
    {
        // Distributed tracing: a caller may have propagated a trace id through
        // the IPC message (see ActorIpcProxy). Continue that trace here so the
        // handler runs inside a child span of the same end-to-end trace.
        $incomingTraceId = $this->extractTraceId($message);
        $span = $incomingTraceId !== null
            ? Tracer::continue($incomingTraceId, 'actor.handle:' . $this->name)
            : Tracer::start('actor.handle:' . $this->name);

        try {
            $this->dispatchMessage($message);
            // A clean handling resets the consecutive-failure counter.
            $this->restartAttempts = 0;
        } catch (\Throwable $throwable) {
            $this->restartAttempts++;
            $directive = $this->supervisorStrategy->decide($throwable, $this->name, $this->restartAttempts);

            $this->error(sprintf(
                "Actor %s failed (attempt %d): %s",
                $this->name,
                $this->restartAttempts,
                $throwable->getMessage()
            ));

            if ($directive->is(Directive::RESUME)) {
                return;
            }

            if ($directive->is(Directive::RESTART)) {
                $this->onRestart();
                return;
            }

            if ($directive->is(Directive::STOP)) {
                $this->destroy();
                return;
            }

            // ESCALATE: hand the failure up to the parent supervisor.
            if ($this->parentName !== null) {
                $this->escalateToParent($throwable);
                return;
            }

            // No parent: rethrow to break the mailbox loop.
            throw $throwable;
        } finally {
            $span->end();
            Tracer::clear();
        }
    }

    /**
     * Extract a propagated trace id from an incoming message, if present.
     *
     * The IPC proxy injects the active trace id under the "__traceId" key so
     * the remote actor can continue the same distributed trace.
     *
     * @param mixed $message
     */
    protected function extractTraceId($message): ?string
    {
        if (!$message instanceof ActorMessage) {
            return null;
        }
        $data = $message->getData();
        if (is_array($data) && isset($data['__traceId']) && is_string($data['__traceId'])) {
            return $data['__traceId'];
        }

        return null;
    }

    /**
     * Report a failure to the parent actor so it can apply its own strategy.
     *
     * @param \Throwable $throwable
     */
    protected function escalateToParent(\Throwable $throwable): void
    {
        $parent = ActorManager::getInstance()->getActor($this->parentName);
        if (!$parent instanceof Actor) {
            // Parent gone: fall back to self-termination.
            $this->destroy();
            return;
        }

        $parent->supervise($this->name, $throwable);
    }

    /**
     * Apply this actor's supervisor strategy to a failing child.
     *
     * @param string     $childName
     * @param \Throwable $throwable
     */
    public function supervise(string $childName, \Throwable $throwable): void
    {
        $child = ActorManager::getInstance()->getActor($childName);
        if (!$child instanceof Actor) {
            return;
        }

        $directive = $this->supervisorStrategy->decide($throwable, $childName, $child->getRestartAttempts());

        if ($directive->is(Directive::RESUME)) {
            return;
        }

        // All-for-one: the directive applies to every sibling, not just the failing child.
        if ($this->actorConfig->getSupervisorMode() === 'all-for-one') {
            $this->applyToAllChildren($directive, $throwable);
            return;
        }

        if ($directive->is(Directive::RESTART)) {
            ActorManager::getInstance()->restartActor($childName);
            return;
        }

        if ($directive->is(Directive::STOP)) {
            $child->destroy();
            return;
        }

        // ESCALATE further up the tree.
        if ($this->parentName !== null) {
            $this->escalateToParent($throwable);
            return;
        }

        $this->error(sprintf("Actor %s: child %s failure escalated and not handled", $this->name, $childName));
    }

    /**
     * Apply a restart/stop directive to every direct child (all-for-one mode).
     *
     * @param Directive   $directive
     * @param \Throwable  $throwable
     */
    protected function applyToAllChildren(Directive $directive, \Throwable $throwable): void
    {
        foreach ($this->getChildren() as $siblingName) {
            $sibling = ActorManager::getInstance()->getActor($siblingName);
            if (!$sibling instanceof Actor) {
                continue;
            }

            if ($directive->is(Directive::RESTART)) {
                ActorManager::getInstance()->restartActor($siblingName);
            } elseif ($directive->is(Directive::STOP)) {
                $sibling->destroy();
            }
        }
    }

    /**
     * Hook invoked before a supervised restart.
     *
     * @return void
     */
    protected function onRestart(): void
    {
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @param int $state
     * @return void
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    /**
     * Init data
     * @param $data
     * @return void
     */
    public function initData($data)
    {
        $this->data = $data;
    }

    /**
     * @param ActorMessage $message
     * @return void
     */
    /**
     * Handle a single mailbox message under supervision. Public so a
     * {@see \Yew\Plugins\Actor\Dispatcher\Dispatcher} can drive execution under a
     * different execution model (pinned / thread-pool).
     *
     * @param ActorMessage $message
     * @return void
     */
    public function onHandleMessage(ActorMessage $message)
    {
        $this->processWithSupervision($message);
    }

    /**
     * Pure message type dispatch (no supervision wrapping).
     *
     * @param ActorMessage $message
     */
    protected function dispatchMessage(ActorMessage $message): void
    {
        $type = $message->getType();

        switch ($type) {
            case ActorMessage::TYPE_MULTICAST:
                $this->handleMulticastMessage($message);
                break;

            case ActorMessage::TYPE_COMMON:
            default:
                // Typed routing: if the payload is a Message carrying a
                // MessageType, dispatch via the registered handler table.
                $data = $message->getData();
                if ($data instanceof Message) {
                    $this->routeTypedMessage($data);
                    return;
                }
                // Legacy weakly-typed path (data bag) — kept for compatibility.
                $this->handleMessage($message);
        }
    }

    /**
     * Route a typed message through the handler table registered via
     * {@see registerHandler()}. Falls back to {@see handleTyped()} for
     * unregistered types so subclasses can still override centrally.
     *
     * @param Message $message
     */
    protected function routeTypedMessage(Message $message): void
    {
        $key = $message->type()->getName();
        if (isset($this->messageHandlers[$key])) {
            ($this->messageHandlers[$key])($message, $this);
            return;
        }

        // No registered handler: defer to the subclass hook (or no-op).
        $this->handleTyped($message);
    }

    /**
     * Register a handler for a specific typed message.
     *
     * @param MessageType $type
     * @param callable    $handler Signature: (Message $message, static $actor): void
     */
    protected function registerHandler(MessageType $type, callable $handler): void
    {
        $this->messageHandlers[$type->getName()] = $handler;
    }

    /**
     * Central hook for typed messages without a registered handler.
     * Subclasses opt in; default is a no-op.
     *
     * @param Message $message
     */
    protected function handleTyped(Message $message): void
    {
        // Override in subclasses to handle typed messages centrally.
    }

    /**
     * Resolve the configured execution-model dispatcher by name.
     *
     * @param string $name One of "coroutine", "pinned", "thread-pool"
     */
    protected function resolveDispatcher(string $name): Dispatcher
    {
        switch (strtolower($name)) {
            case 'pinned':
                return new PinnedDispatcher();
            case 'thread-pool':
            case 'threadpool':
                return new ThreadPoolDispatcher($this->actorConfig->getDispatcherPoolSize());
            case 'coroutine':
            default:
                return new CoroutineDispatcher();
        }
    }

    /**
     * Access the execution-model dispatcher (e.g. to offload CPU-bound work).
     *
     * @return Dispatcher
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    abstract protected function handleMulticastMessage(ActorMessage $message);
    
    /**
     * @param ActorMessage $message
     * @return mixed
     */
    abstract protected function handleMessage(ActorMessage $message);

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int Consecutive failure count (for parent supervisors)
     */
    public function getRestartAttempts(): int
    {
        return $this->restartAttempts;
    }

    /**
     * @return string|null Parent actor name, or null for a root actor
     */
    public function getParentName(): ?string
    {
        return $this->parentName;
    }

    /**
     * @param string|null $parentName
     */
    public function setParentName(?string $parentName): void
    {
        $this->parentName = $parentName;
    }

    /**
     * @return string[] Names of direct children
     */
    public function getChildren(): array
    {
        return ActorManager::getInstance()->getChildren($this->name);
    }

    /**
     * Destroy
     */
    public function destroy()
    {
        $this->clearAllTimer();
        if ($this->dispatcher instanceof PinnedDispatcher) {
            $this->dispatcher->shutdown();
        }
        ActorManager::getInstance()->removeActor($this);
    }

    /**
     * Recovery (event sourcing).
     *
     * Rebuilds actor state by loading the latest snapshot, then replaying all
     * events that occurred after it. No-op when persistence is disabled.
     *
     * @return void
     */
    public function recovery()
    {
        if ($this->store === null) {
            $this->setState(2);
            return;
        }

        $snapshot = $this->store->loadSnapshot($this->name);
        if ($snapshot !== null) {
            $this->data = $snapshot->getState();
            $this->eventSequence = $snapshot->getLastSequence();
        }

        foreach ($this->store->loadEvents($this->name) as $event) {
            if ($event->getSequence() <= $this->eventSequence) {
                continue; // already captured by the snapshot
            }
            $this->apply($event);
            $this->eventSequence = $event->getSequence();
        }

        $this->setState(2);
    }

    /**
     * Persist an event and apply it to the current state.
     *
     * This is the single write path for durable state changes. Subclasses call
     * persist() instead of mutating $data directly, so the change is both
     * recorded (event log) and applied (in-memory state).
     *
     * @param string $type    Event type, e.g. "Deposit", "Increment"
     * @param mixed  $payload Event payload
     */
    protected function persist(string $type, $payload): void
    {
        if ($this->store === null) {
            // Persistence disabled: still apply in-memory so behavior is consistent.
            $this->apply(new ActorEvent($this->name, $type, $payload, microtime(true), $this->eventSequence));
            return;
        }

        $this->eventSequence++;
        $event = new ActorEvent($this->name, $type, $payload, microtime(true), $this->eventSequence);
        $this->store->appendEvent($event);
        $this->apply($event);
    }

    /**
     * Apply an event to the actor's state (left-fold of the event log).
     *
     * Override this in subclasses to mutate $this->data / derived state.
     * Must be idempotent: it runs both on persist and on recovery replay.
     *
     * @param ActorEvent $event
     * @return void
     */
    protected function apply(ActorEvent $event): void
    {
        // Default: merge payload into data. Subclasses should override for typed logic.
        if (is_array($event->getPayload())) {
            $this->data = array_merge($this->data, $event->getPayload());
        }
    }

    /**
     * Write a snapshot of the current state to the store.
     *
     * @return void
     */
    protected function takeSnapshot(): void
    {
        if ($this->store === null) {
            return;
        }

        $this->store->saveSnapshot(new Snapshot(
            $this->name,
            $this->data,
            $this->eventSequence,
            microtime(true)
        ));
    }

    /**
     * Delete all persisted state for this actor (events + snapshot).
     *
     * @return void
     */
    protected function clearPersisted(): void
    {
        if ($this->store === null) {
            return;
        }

        $this->store->delete($this->name);
    }


    /**
     * Get proxy
     * @param string $actorName
     * @param bool $oneWay
     * @param float|null $timeOut
     * @return \Yew\Plugins\Actor\ActorIpcProxy|false
     */
    public static function getProxy(string $actorName, ?bool $oneWay = false, ?float $timeOut = 5)
    {
        try {
            return new ActorIpcProxy($actorName, $oneWay, $timeOut);
        } catch (ActorException $exception) {
            return false;
        }
    }

    

    /**
     * Enqueue a message into the mailbox, applying the configured overflow strategy.
     *
     * @param ActorMessage $message
     * @return bool True if enqueued, false if dropped (drop strategy) or rejected.
     */
    public function sendMessage(ActorMessage $message): bool
    {
        try {
            $pushed = $this->mailboxOverflowStrategy->enqueue(
                $this->channel,
                $message,
                $this->actorConfig->getMailboxPushTimeout()
            );
        } catch (ActorException $exception) {
            $this->error(sprintf("Actor %s mailbox rejected message: %s", $this->name, $exception->getMessage()));
            return false;
        }

        if ($pushed === false) {
            $this->warning(sprintf("Actor %s mailbox full, message dropped", $this->name));
        }

        return $pushed;
    }

    /**
     * Tick timer
     * @param int $msec
     * @param callable $callback
     * @param ...$params
     * @return false|int
     */
    public function tick(int $msec, callable $callback, ... $params)
    {
        $id = Timer::tick($msec, $callback, ...$params);
        $this->timerIds[$id] = $id;

        return $id;
    }

    /**
     * After timer
     * @param int $msec
     * @param callable $callback
     * @param ...$params
     * @return int
     */
    public function after(int $msec, callable $callback, ... $params): int
    {
        $id = Timer::after($msec, $callback, ...$params);
        $this->timerIds[$id] = $id;

        return $id;
    }

    /**
     * Clear timer
     * @param int $id
     * @return void
     */
    public function clearTimer(int $id)
    {
        Timer::clear($id);
        unset($this->timerIds[$id]);
    }

    /**
     * Clear all timer
     * @return void
     * @throws \Exception
     */
    public function clearAllTimer(): bool
    {
        if (!empty($this->timerIds)) {
            foreach ($this->timerIds as $timerId) {
                $this->clearTimer($timerId);
            }
            $this->debug(sprintf("Actor %s's all timer cleared", $this->getName()));
        }
        return true;
    }

    /**
     * Periodic persistence hook.
     *
     * When persistence is enabled, writes a snapshot of the current state so that
     * recovery only needs to replay events after this point. When disabled, falls
     * back to the original debug log behavior.
     *
     * @return void
     */
    public function saveContext(): void
    {
        if ($this->store !== null) {
            $this->takeSnapshot();
            return;
        }

        Server::$instance->getLog()->debug(__METHOD__);
        $this->logHandle->log($this->data);
    }
}
