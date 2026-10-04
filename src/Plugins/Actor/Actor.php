<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Channel\Channel;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Dispatcher\PinnedDispatcher;
use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Plugins\Actor\Log\LogFactory;
use Yew\Plugins\Actor\Multicast\Multicast;
use Yew\Plugins\Actor\Persistence\ActorEvent;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use Yew\Plugins\Actor\Persistence\Snapshot;
use Yew\Plugins\Actor\Telemetry\ActorTelemetry;
use Swoole\Timer;

/**
 * Business-facing actor base class.
 *
 * Extend this — not {@see BaseActor} — to write an actor. It exposes the surface
 * a subclass actually works with:
 *  - lifecycle hooks: init() / postStop() / preRestart() / onRestart() / onRecovered()
 *  - persistence:     persist() / apply() / recovery() / takeSnapshot() / saveContext()
 *  - timers:          tick() / after() / clearTimer() / clearAllTimer()
 *  - mailbox:         sendMessage()
 *
 * Framework machinery (construction, strategy resolution, supervision, message
 * dispatch, multicast forwarding) lives in {@see BaseActor}.
 *
 * Subclasses must implement handleMessage() and handleMulticast().
 */
abstract class Actor extends BaseActor
{
    /**
     * @return void
     */
    protected function init()
    {
        $this->initChannel();

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

    /**
     * Create the mailbox channel and resolve the overflow strategy.
     */
    protected function initChannel()
    {
        $this->channel = DIGet(Channel::class, [$this->actorConfig->getMailboxCapacity()]);
        $this->mailboxOverflowStrategy = $this->resolveOverflowStrategy($this->actorConfig->getMailboxOverflow());
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
     * Get the actor's durable state snapshot.
     *
     * This array is the single source of truth that gets persisted (snapshot /
     * event payload), replicated across nodes, and carried over on a supervisor
     * restart. Keep it serializable and small: store big payloads in an external
     * store (Swoole Table / Redis / …) and keep only a reference key here, so
     * IPC and replication stay cheap.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Restore the actor's durable state (recovery / supervision restart).
     *
     * @param array $data Lightweight, serializable state — see {@see getData()}.
     */
    public function setData(array $data): void
    {
        $this->data = $data;
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
     * Serialize only what is needed to reconstruct the actor elsewhere.
     *
     * Runtime dependencies (dispatcher, logger, channel, message handlers,
     * timer ids, …) are intentionally excluded — they are rebuilt by init()
     * on the target side and, more importantly, hold closures/callables that
     * cannot be serialized at all. This keeps the payload equal to the actor's
     * business state instead of the whole object graph.
     */
    public function __serialize(): array
    {
        return [
            'name'       => $this->name,
            'parentName' => $this->parentName,
            'state'      => $this->state,
            'data'       => $this->data,
        ];
    }

    /**
     * Restore the serializable fields.
     *
     * Runtime dependencies are left for init() to (re)inject; we only revive
     * the identifying and stateful fields here.
     */
    public function __unserialize(array $data): void
    {
        $this->name       = $data['name'];
        $this->parentName = $data['parentName'];
        $this->state      = $data['state'];
        $this->data       = $data['data'];
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
        $this->postStop();
        ActorManager::getInstance()->removeActor($this);
    }

    /**
     * Hook invoked right before this actor is torn down (explicit stop, parent
     * STOP directive, or process shutdown). Override to release resources that
     * live outside the mailbox (sockets, files, external locks). The actor is
     * still registered at this point, so you can safely read its state.
     *
     * @return void
     */
    protected function postStop(): void
    {
    }

    /**
     * Hook invoked just before a supervised restart replaces this instance.
     * Override to save off transient state or release resources that must not
     * survive the restart. The fresh instance will run init()/onRestart() next.
     *
     * @return void
     */
    public function preRestart(): void
    {
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
            $this->onRecovered();
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
        $this->onRecovered();
    }

    /**
     * Hook invoked once durable state is fully loaded, i.e. right after
     * recovery() has replayed the snapshot and the event log.
     *
     * Unlike init() — which runs BEFORE recovery() and therefore still sees an
     * empty $this->data — this hook can safely read the restored state. Use it
     * to re-arm work derived from that state, e.g. re-scheduling a one-shot
     * delay from a persisted expiry timestamp rather than a raw interval.
     *
     * Also invoked for freshly created actors (with empty state), so keep the
     * implementation idempotent.
     *
     * @return void
     */
    protected function onRecovered(): void
    {
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
        // Persist a snapshot after each state change so external readers (e.g.
        // the HTTP controller reading the shared snapshot file directly,
        // bypassing the actor's serial IPC queue) always observe a fresh value.
        // Snapshot write is a single atomic file rename; state is typically
        // small, so the extra cost per persist is negligible. recovery() still
        // replays only events after the snapshot's lastSequence, so this stays
        // consistent.
        $this->saveContext();
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
     *
     * One-shot: Swoole destroys the timer as soon as it fires, so the id is
     * dropped from the registry before running the callback. Otherwise an actor
     * that schedules many delays would keep every fired id in $timerIds forever.
     *
     * @param int $msec
     * @param callable $callback
     * @param ...$params
     * @return int
     */
    public function after(int $msec, callable $callback, ... $params): int
    {
        $id = Timer::after($msec, function (...$args) use (&$id, $callback) {
            unset($this->timerIds[$id]);

            return $callback(...$args);
        }, ...$params);

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

        $this->logHandle->log($this->data);
    }
}
