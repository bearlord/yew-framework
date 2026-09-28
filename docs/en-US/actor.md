# Actor

Yew provides an Akka-style actor model: units of state and behavior that communicate only
by exchanging messages, each running inside isolated `actor` worker processes with its own
mailbox. Actors give you location-transparent concurrency, supervision, and (with the
Cluster plugin) transparent cross-node distribution.

Return to [homepage](../README.md) · See also [Multicast](./multicast.md) · [Cluster](./clusterm.md).

---

## 1. Overview

- Each actor has a **mailbox** (a Swoole Channel / Table) and processes one message at a time.
- You talk to an actor through an **`ActorIpcProxy`** — a location-transparent handle. Whether
  the actor lives in the same process, another worker, or another node is invisible to you.
- Three message semantics: `tell` (fire-and-forget), `ask` (request-response, blocking),
  `askFuture` (request-response, async future).
- Built-in **supervision** (restart / resume / stop / escalate), **routing**
  (round-robin / consistent-hash / least-loaded) and optional **event sourcing**.
- With the [Cluster](./clusterm.md) plugin enabled, `ActorIpcProxy` transparently routes
  messages to actors on other nodes.

> Actors can **only** be created in a process whose name contains `"actor"`
> (`ActorPlugin` starts `actor-N` worker processes). You must use `ActorSystem::create/actorOf`,
> never `new`.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Actor\ActorPlugin;

$app->addPlugin(new ActorPlugin());
// If you use Cluster: ClusterPlugin must be added BEFORE ActorPlugin.
```

`ActorPlugin` automatically pulls in `IpcPlugin`. It also depends on `MulticastPlugin`
ordering (`MulticastPlugin` is `atAfter(ActorPlugin)`).

---

## 3. Configuration

All keys live under **`yew.actor`** (`ActorConfig`).

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `maxCount` | int | `10000` | Max simultaneous actors (shared memory table capacity) |
| `maxClassCount` | int | `100` | Actor class-map capacity |
| `workerCount` | int | `1` | Number of `actor-N` worker processes |
| `mailboxCapacity` | int | `100` | Per-actor mailbox (Swoole Channel) capacity |
| `mailboxOverflow` | string | `"block"` | `block` \| `drop` \| `fail` when full |
| `mailboxPushTimeout` | float | `1.0` | Max seconds to wait for free space (`block`) |
| `supervisorStrategy` | string | `"restart"` | `restart` \| `resume` \| `stop` \| `escalate` |
| `supervisorMaxRetries` | int | `3` | Retry limit before escalating |
| `supervisorMode` | string | `"one-for-one"` | `one-for-one` \| `all-for-one` |
| `persistenceEnabled` | bool | `false` | Enable event-sourcing persistence |
| `persistenceDir` | string | `/tmp/yew-actor-store` | File store directory |
| `routingStrategy` | string | `"round-robin"` | `round-robin` \| `consistent-hash` \| `least-loaded` |
| `routingReplicas` | int | `128` | Virtual replicas per node (consistent hash) |
| `dispatcher` | string | `"coroutine"` | `coroutine` \| `pinned` \| `thread-pool` |
| `dispatcherPoolSize` | int | `4` | Thread-pool worker count |
| `telemetryEnabled` | bool | `false` | Per-actor metrics / tracing |

> Cluster-related settings (`clusterEnabled`, `clusterNodeId`, `clusterHost`, `clusterPort`,
> `clusterWeight`, `clusterGossipPort`, `clusterSeeds`, `clusterSecret`, …) are **mirrored
> from `yew.cluster`**, not from `yew.actor`. Configure them under `yew.cluster`
> (see [Cluster](./clusterm.md)).

```yaml
yew:
  actor:
    workerCount: 2
    mailboxCapacity: 10000
    supervisorStrategy: restart
    routingStrategy: consistent-hash
```

---

## 4. Core API

### Defining an actor

```php
abstract class Yew\Plugins\Actor\Actor
{
    abstract protected function handleMessage(ActorMessage $message);
    abstract protected function handleMulticast(ActorMessage $message);
}
```

Common overridable hooks:

- State: `initData($data)`, `getData(): array`, `setData(array $data)`.
- Lifecycle: `onRestart()`, `preRestart()`, `postStop()`.
- Persistence: `recovery()`, `persist(string $type, $payload)`, `apply(ActorEvent $e)`,
  `takeSnapshot()`, `clearPersisted()`.
- Timers: `tick(int $msec, callable $cb)`, `after(...)`, `clearTimer($id)`, `clearAllTimer()`.
- Pub/sub: `subscribe(string $channel)`, `unsubscribe`, `unsubscribeAll`, `hasChannel`,
  `publish(channel, message, excludeActorList=[])`, `publishTo`, `publishIn`.
- Typed messages: `registerHandler(MessageType $type, callable $handler)`, `handleTyped`.
- Control: `destroy()`, `getName()`, `getState()`.

### Creating actors

```php
// Yew\Plugins\Actor\ActorSystem
ActorSystem::create(string $actionClass, string $actorName, $data = null,
    bool $waitCreate = true, float $timeOut = 5, ?string $parentName = null,
    ?string $routingKey = null, bool $pinLocal = false): ActorIpcProxy|false;

ActorSystem::actorOf(string $actionClass, $props = null, ?string $actorName = null): ActorIpcProxy|false;

ActorSystem::has(string $actorName): bool;
ActorSystem::get(string $actorName, bool $oneWay = false, float $timeOut = 5);
ActorSystem::wait(string $actorName, float $timeOut = 5);
ActorSystem::destroy(string $actorName, bool $waitDelete = true, float $timeOut = 5): bool;
```

`Props` (immutable builder): `Props::create(FooActor::class, $data)`
→ `withName()` · `withParentName()` · `withRoutingKey()` · `withWaitCreate()` ·
`withTimeOut()` · `withPinLocal()`.

### Sending messages (via `ActorIpcProxy`)

```php
$proxy->tell(string $method, array $arguments = []): bool;          // fire-and-forget
$proxy->ask(string $method, array $arguments = [], float $timeOut = 0);  // blocking
$proxy->askFuture(string $method, array $arguments = [], float $timeOut = 0): ActorFuture;
$proxy->__call(string $method, array $arguments);                   // magic, non-oneway ask
$proxy->isRemote(): bool;
```

`ActorFuture`: `await(float $timeout = 0)`, `then(callable $onFulfilled)`, `isComplete()`,
`resolve($v)`, `reject(\Throwable $e)`.

> Lifecycle control (`destroy` / `stop` / …) is **forbidden** over IPC — always call
> `ActorSystem::destroy($actorName)`.

---

## 5. Usage Example

```php
use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorMessage;
use Yew\Plugins\Actor\ActorSystem;
use Yew\Plugins\Actor\Props;

class CounterActor extends Actor
{
    protected function handleMessage(ActorMessage $message)
    {
        $this->data['count'] = ($this->data['count'] ?? 0) + 1;
        return $this->data['count'];   // returned value becomes the ask() result
    }

    protected function handleMulticast(ActorMessage $message)
    {
        // received a TYPE_MULTICAST message (see Multicast)
    }
}

// create (returns an IPC proxy)
/** @var \Yew\Plugins\Actor\ActorIpcProxy $proxy */
$proxy = ActorSystem::actorOf(
    CounterActor::class,
    Props::create(CounterActor::class)->withName('counter-1')->withData(['count' => 0])
);

$proxy->tell('handleMessage', [$someData]);                  // fire-and-forget
$count = $proxy->ask('handleMessage', [$someData]);          // blocking result
$future = $proxy->askFuture('handleMessage', [$someData]);
$count = $future->await(5.0);                                // async
```

---

## 6. Dependencies & Plugin Order

- **IpcPlugin** — required, auto-added by `ActorPlugin` (`atAfter(IpcPlugin::class)`).
- **ClusterPlugin** — optional; if `yew.cluster.enabled`, `ActorPlugin` injects a real
  `ShardRouter` / `RemoteTransport` so proxies become `isRemote()`.
- Order: `Ipc` → `Actor` → `Multicast`.

---

## 7. Notes / Caveats

- Create actors only inside an `actor` process; never `new` an actor.
- Use `ActorSystem::destroy`, not IPC, for lifecycle methods.
- With Cluster enabled, routing is consistent-hash by default; on node up/down the
  `ShardRouter` rebalances and may evict actors no longer owned locally.
- `@Value` config variables are **not** auto-registered from YAML (see
  [Getting Started](./getting-started.md) §7).

---

## 8. Related

- [Multicast](./multicast.md) — topic pub/sub between actors
- [Cluster](./clusterm.md) — cross-node actor transport & failover
- [Getting Started](./getting-started.md) — config, plugin lifecycle, DI
