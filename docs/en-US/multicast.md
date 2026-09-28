# Multicast (Pub/Sub)

Multicast provides topic-based publish/subscribe between actors. Subscriptions are kept in a
dedicated `multicast` helper process (backed by a cross-process Swoole Table), and messages can
optionally be fanned out to other cluster nodes over UDP/Gossip. Topics support MQTT-style
wildcards `+` (single level) and `#` (multi-level).

Return to [homepage](../README.md) · See also [Actor](./actor.md) · [Cluster](./clusterm.md).

---

## 1. Overview

- Works **inside actors**: call `subscribe()` / `publish()` on `$this` (an `Actor`).
- Wildcards: `+` matches exactly one topic level; `#` matches the rest of the topic tree.
- A message delivered to a subscriber arrives as an `ActorMessage` of type
  `TYPE_MULTICAST`; your actor handles it in `handleMulticast()`.
- Optional cross-node fan-out requires the [Cluster](./clusterm.md) plugin.

---

## 2. Installation & Plugin Registration

`MulticastPlugin` is normally auto-pulled in by `ActorPlugin` (`atAfter(ActorPlugin)`), so you
usually only need `ActorPlugin`. To be explicit:

```php
use Yew\Plugins\Actor\ActorPlugin;
use Yew\Plugins\Actor\Multicast\MulticastPlugin;

$app->addPlugin(new ActorPlugin());
$app->addPlugin(new MulticastPlugin());
```

---

## 3. Configuration

Keys live under **`yew.multicast`** (`MulticastConfig`).

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `clusterEnabled` | bool | `false` | Also fan messages out to other cluster nodes |
| `clusterPort` | int | `0` | Cluster multicast UDP port; `0` = `gossipPort + 1`; `>0` enables fan-out |
| `broadcaster` | `ClusterBroadcaster\|null` | `null` | Injected by the plugin; usually leave unset |
| `cacheChannelCount` | int | `10000` | Cross-process subscription table (Swoole Table) channel rows |
| `channelMaxLength` | int | `256` | Max channel name length |
| `cacheActorCount` | int | `10000` | Subscription table actor rows |
| `actorMaxLength` | int | `256` | Max actor name length |
| `processName` | string | `"multicast"` | Helper process name |

> The internal Swoole Channel capacity is read from `actor.multicastChannelCapacity`
> (default `10000`) in `yew.actor`.

```yaml
yew:
  multicast:
    clusterEnabled: true
    clusterPort: 50000   # must differ from cluster.gossipPort and be > 0
```

---

## 4. Core API

### Inside an actor

```php
// Yew\Plugins\Actor\Actor (delegates to Multicast)
public function subscribe(string $channel): void;
public function unsubscribe(string $channel): void;
public function unsubscribeAll(): void;
public function hasChannel(string $channel): bool;
public function publish(string $channel, string $message, array $excludeActorList = []): void; // excludes self
public function publishTo(string $channel, string $message): void;   // others only
public function publishIn(string $channel, string $message): void;   // includes self
```

### Outside an actor (use the `GetMulticast` trait)

```php
use Yew\Plugins\Actor\Multicast\GetMulticast;

$dispatcher->actorSubscribe(string $channel, string $actor): void;
$dispatcher->actorUnsubscribe(string $channel, string $actor): void;
$dispatcher->actorUnsubscribeAll(string $actor): void;
$dispatcher->actorHasChannel(string $channel, string $actor): bool;
$dispatcher->actorPublish(string $channel, ?string $message, array $excludeActorList = []): void;
$dispatcher->deleteChannel(string $channel): void;
```

### Wildcards

- `+` — single level, must occupy a whole level (`a/+/c` valid; `a/b+/c` invalid).
- `#` — multi-level, must be the final character (`a/#` valid; `a/#/c` invalid).
- Channel length `1..256`; `$`-prefixed system topics have special handling.

---

## 5. Usage Example

```php
use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorMessage;

class ChatActor extends Actor
{
    protected function handleMessage(ActorMessage $message)
    {
        $this->subscribe('room/+/chat');                       // subscribe
        $this->publish('room/42/chat', json_encode(['u' => 'alice', 't' => 'hi']));
    }

    protected function handleMulticast(ActorMessage $message)
    {
        $payload = $message->getData();   // ['channel' => ..., 'type' => 'multicast', 'message' => ...]
        // $payload['message'] is the published payload
    }
}
```

From a controller / worker (non-actor context) via the trait:

```php
use Yew\Plugins\Actor\Multicast\GetMulticast;

class SomeController
{
    use GetMulticast;
    public function broadcast(): void
    {
        $this->actorSubscribe('room/42/chat', 'chat-actor-1');
        $this->actorPublish('room/42/chat', 'hello every node');
    }
}
```

---

## 6. Dependencies & Plugin Order

- `MulticastPlugin` is `atAfter(ActorPlugin::class)`.
- Cluster fan-out is wired only when `clusterEnabled` **and** `GossipClusterState` exists
  **and** `clusterPort > 0`. It uses `GetIpc` to call the `multicast` process.
- If cluster is not enabled, the plugin logs a warning and degrades to local-only delivery.

---

## 7. Notes / Caveats

- Subscriptions persist in a cross-process table; a worker restart does not lose them.
- Cross-node delivery uses a **separate UDP port** from the cluster gossip port — keep them distinct.
- `publish()` excludes the publisher by default; use `publishIn()` to also receive locally.

---

## 8. Related

- [Actor](./actor.md) · [Cluster](./clusterm.md) · [Getting Started](./getting-started.md)
