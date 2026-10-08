<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use DI\Attribute\Inject;
use Yew\Core\Channel\Channel;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Dispatcher\CoroutineDispatcher;
use Yew\Plugins\Actor\Dispatcher\Dispatcher;
use Yew\Plugins\Actor\Dispatcher\PinnedDispatcher;
use Yew\Plugins\Actor\Dispatcher\ThreadPoolDispatcher;
use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Plugins\Actor\Log\Logger;
use Yew\Plugins\Actor\Mailbox\BlockStrategy;
use Yew\Plugins\Actor\Mailbox\DropStrategy;
use Yew\Plugins\Actor\Mailbox\FailStrategy;
use Yew\Plugins\Actor\Mailbox\MailboxOverflowStrategy;
use Yew\Plugins\Actor\Message\Message;
use Yew\Plugins\Actor\Message\MessageType;
use Yew\Plugins\Actor\Multicast\Multicast;
use Yew\Plugins\Actor\Multicast\MulticastConfig;
use Yew\Plugins\Actor\Persistence\ActorStore;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Supervision\Directive;
use Yew\Plugins\Actor\Supervision\EscalateStrategy;
use Yew\Plugins\Actor\Supervision\ResumeStrategy;
use Yew\Plugins\Actor\Supervision\RestartStrategy;
use Yew\Plugins\Actor\Supervision\StopStrategy;
use Yew\Plugins\Actor\Supervision\SupervisorStrategy;
use Yew\Plugins\Actor\Telemetry\Tracer;

/**
 * Framework plumbing shared by every actor.
 *
 * This layer owns the machinery an actor needs but a business subclass rarely
 * touches: construction, strategy resolution, the supervision tree, mailbox
 * dispatch and multicast forwarding. {@see Actor} sits on top of it and exposes
 * the business-facing surface (lifecycle hooks, persistence, timers).
 *
 * Business code must extend {@see Actor}, never this class.
 */
abstract class BaseActor
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

    #[Inject]
    protected ?ClusterActorStore $injectedStore = null;

    /**
     * @var int Monotonic event sequence for this actor (event sourcing)
     */
    protected int $eventSequence = 0;

    /**
     * @var Channel
     */
    protected $channel;

    #[Inject]
    protected EventDispatcher $eventDispatcher;

    #[Inject]
    protected ?ActorConfig $actorConfig = null;

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

        // Defensive: injectOn may not populate the typed ActorConfig property
        // (e.g. when the annotation reader is disabled). Fall back to the
        // container-resolved instance so it is never left uninitialized.
        if (!isset($this->actorConfig)) {
            $this->actorConfig = ActorManager::getInstance()->getActorConfig();
        }

        $this->init();

        $this->recovery();
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
        if (!$parent instanceof BaseActor) {
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
        if (!$child instanceof BaseActor) {
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
            if (!$sibling instanceof BaseActor) {
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
                $this->handleMulticast($message);
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
     * Access the execution-model dispatcher (e.g. to offload CPU-bound work).
     *
     * @return Dispatcher
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher;
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
     * Subscribe this actor to a multicast channel.
     *
     * Cross-process safe: when invoked on an ActorIpcProxy, __call forwards the
     * call to the owning process, so $actor->subscribe() works regardless of
     * where the actor lives.
     */
    public function subscribe(string $channel): void
    {
        $this->multicast()->subscribe($channel);
    }

    /**
     * Unsubscribe this actor from a multicast channel.
     */
    public function unsubscribe(string $channel): void
    {
        $this->multicast()->unsubscribe($channel);
    }

    /**
     * Unsubscribe this actor from all multicast channels.
     */
    public function unsubscribeAll(): void
    {
        $this->multicast()->unsubscribeAll();
    }

    /**
     * Whether this actor has subscribed to the given channel.
     */
    public function hasChannel(string $channel): bool
    {
        return $this->multicast()->hasChannel($channel);
    }

    /**
     * Publish a message to a channel, excluding this actor by default.
     */
    public function publish(string $channel, string $message, array $excludeActorList = []): void
    {
        $this->multicast()->publish($channel, $message, $excludeActorList);
    }

    /**
     * Publish a message to a channel, delivered only to other subscribers.
     */
    public function publishTo(string $channel, string $message): void
    {
        $this->multicast()->publishTo($channel, $message);
    }

    /**
     * Publish a message to a channel, including this actor itself.
     */
    public function publishIn(string $channel, string $message): void
    {
        $this->multicast()->publishIn($channel, $message);
    }

    /**
     * Resolve the Multicast facade bound to this actor, falling back to a fresh
     * instance when the injected $multicast property is not yet initialized.
     */
    public function multicast(): Multicast
    {
        if (!isset($this->multicast)) {
            return new Multicast($this->name, DIGet(MulticastConfig::class));
        }

        return $this->multicast;
    }

    /**
     * Business contract: handle a point-to-point message.
     *
     * Implemented by the concrete actor (see {@see Actor}).
     *
     * @param ActorMessage $message
     * @return mixed
     */
    abstract protected function handleMessage(ActorMessage $message);

    /**
     * Business contract: handle a multicast message.
     *
     * Implemented by the concrete actor (see {@see Actor}).
     *
     * @param ActorMessage $message
     */
    abstract protected function handleMulticast(ActorMessage $message);

    /**
     * Wired up by {@see Actor}: build the mailbox, dispatcher and persistence.
     *
     * @return void
     */
    abstract protected function init();

    /**
     * Wired up by {@see Actor}: rebuild durable state (event sourcing).
     *
     * @return void
     */
    abstract public function recovery();

    /**
     * Wired up by {@see Actor}: tear the actor down.
     *
     * @return void
     */
    abstract public function destroy();

    /**
     * Wired up by {@see Actor}: hook invoked before a supervised restart.
     *
     * @return void
     */
    abstract protected function onRestart(): void;
}
