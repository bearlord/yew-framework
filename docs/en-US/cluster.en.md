# Yew Cluster — Usage Guide (English)

> Applies to: Yew framework `Yew\Cluster` package (`vendor/bearlord/yew-framework/src/Cluster`)
> Keywords: Gossip membership, consistent-hash shard routing, cross-node Actor transport, cluster broadcast, Actor replication & failover

---

## 1. Overview

`Yew\Cluster` is Yew's clustering package. It provides **location-transparent** distributed primitives for the Actor layer:

- **Membership**: Gossip-based cluster view (SYN/SYN-ACK/ACK handshake, digest anti-entropy, UDP fragmentation & retransmit, failure detection / FD).
- **Shard Router**: maps an actor name to its owning node via consistent hashing, enabling key-based sharding and rebalancing.
- **Remote Transport**: cross-node Actor `tell` / `ask` / `create` (Akka remoting / Orleans silo-to-silo equivalent).
- **Broadcaster**: fans a message out to every other alive node in the cluster.
- **Actor Replication & Failover**: replicates actor events/snapshots to replica nodes; on node death, survivors resurrect its actors.

### Architecture: single authority process

Cluster state lives **only** in each node's `cluster-state` helper process. Worker processes query it read-only over IPC (`GetClusterState`), so:

- Every worker sees exactly the same member view and shard ring — **no per-worker disagreement, no split-brain**;
- No cross-worker shared `Swoole\Table` is used;
- The Gossip UDP socket is self-managed by the `cluster-state` process (`UdpGossipTransport`, `setManaged(false)`); it alone sends/receives Gossip traffic.

```
        +-------------------+        IPC (GetClusterState)        +----------------------+
        |  cluster-state    | <---------------------------------- |   worker / actor     |
        |  (authoritative   |    getMemberView / getLocationArray  |   process (read-only) |
        |   view + FD +     |    replicateStoreEntry / findReplica |   IpcShardRouter      |
        |   ring + Gossip)  |    getFailoverActors                 |   PooledTcpRemoteTransport |
        +-------------------+                                      +----------------------+
                  |  UDP Gossip (239.0.0.1:49999 or configured broadcast)
                  v
           other nodes' cluster-state processes
```

---

## 2. Enabling the plugin

`ClusterPlugin` automatically depends on `IpcPlugin` (runs after it) and **must run before** `ActorPlugin` (because `ActorPlugin`'s `beforeServerStart` resolves `ShardRouter` via `DIGet`, requiring `ClusterPlugin` to have registered the concrete `IpcShardRouter` first).

```php
use Yew\Cluster\ClusterPlugin;
use Yew\Plugins\Actor\ActorPlugin;

// In your server bootstrap:
$server->getPlugManager()->addPlug(new ClusterPlugin());
$server->getPlugManager()->addPlug(new ActorPlugin());
```

If you only need the cluster primitives without Actors, `ClusterPlugin` can load on its own (it declares its `IpcPlugin` dependency itself).

> Note: clustering is **off by default**. Only when `yew.cluster.enabled = true` and `beforeServerStart` validation passes does it spawn the `cluster-state` process and wire the cross-node transport. When disabled, `ClusterConfig::isEnabled()` returns `false` and the plugin returns early — behavior is identical to single-node.

---

## 3. Configuration

All cluster config lives under the `yew.cluster` subtree (`Yew\Cluster\ClusterConfig`, `KEY = "cluster"`). Available keys:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | `false` | Enable clustering (sharding + Gossip). |
| `nodeId` | string | `node-<hostname>` | Stable, cluster-unique node id. |
| `host` | string | `127.0.0.1` | Bind host advertised to peers. |
| `port` | int | `0` | Bind port advertised to peers. |
| `weight` | int | `1` | Node capacity; higher = more shards. |
| `suspectAfter` | int | `3` | Seconds of missed heartbeat before *suspect*. |
| `downAfter` | int | `8` | Seconds of missed heartbeat before *down*. |
| `heartbeatInterval` | float | `1.0` | Membership heartbeat / FD tick (seconds). |
| `gossipHost` | string | `0.0.0.0` | Gossip UDP bind address. |
| `gossipPort` | int | `port + 1000` | Gossip UDP port (default = host port + 1000). |
| `gossipBroadcast` | string | `239.0.0.1:49999` | Gossip broadcast/multicast target (`host:port`). |
| `seeds` | string[] | `[]` | Seed peers for first contact (`host:port`). |
| `poolSize` | int | `16` | Cross-node TCP connection pool size per node. |
| `secret` | string | `''` | Shared HMAC secret for Gossip signing (anti-spoofing). |
| `clockSkew` | int | `30` | Allowed clock skew (seconds) for message freshness. |
| `privateKey` | string | `''` | This node's private key PEM; when set, switches to per-node asymmetric signing. |
| `publicKey` | string | `''` | This node's public key PEM (paired with `privateKey`). |
| `trustStore` | array | `[]` | Pinned trust store: `nodeId => publicKey PEM`. When non-empty, only pinned nodes are accepted. |
| `replicationFactor` | int | `2` | Number of replicas an actor's events/snapshots are replicated to (besides owner). Read quorum = 1 replica present. |
| `replicas` | int | `128` | Virtual replica points per node on the consistent-hash ring (higher = more even). |
| `services` | array | `[]` | Declarative service overrides: `state` / `gossip` / `router` / `transport` / `store`. |

Additionally, `yew.cluster.peers` (`nodeId => "host:port"`) gives an explicit static peer map; if omitted, synthetic ids are derived from `seeds`.

The cross-node inbound TCP port must be declared under `yew.port` with the fixed name **`cluster-tcp`**:

```yaml
yew:
  port:
    cluster-tcp:
      host: 0.0.0.0
      port: 9501
```

---

## 4. Core concepts

### ClusterNode (node descriptor)
```php
$node = new ClusterNode($nodeId, $host, $port, $local);
$node->getNodeId();   // stable node id
$node->getHost();     // host/ip
$node->getPort();     // listening port (0 = not network-reachable)
$node->isLocal();     // whether this is the local node
$node->getEndpoint(); // e.g. "node-1@10.0.0.1:9501"
```

### Location (physical actor location)
`Location` is the single source of truth for "where is actor X", making addressing location-transparent:
```php
$location->getNode();       // owning ClusterNode
$location->getProcessId();  // worker process id hosting the actor
$location->getActorName();  // optional actor name
$location->isLocal();       // whether on the local node
```

### Member status
The member view (`getMemberView()`) carries a `status` per node: typically `UP`, `SUSPECT`, `DOWN`. A `DOWN` triggers rebalancing and failover candidates.

---

## 5. Querying cluster state (GetClusterState)

In any Actor / Worker, `use Yew\Cluster\GetClusterState;` exposes helpers that forward over IPC to the `cluster-state` process:

```php
use Yew\Cluster\GetClusterState;

class MyActor
{
    use GetClusterState;

    public function demo(): void
    {
        // 1) Resolve the owning node of an actor (location-transparent)
        $loc = $this->clusterLocate('my-actor');   // Location|null
        if ($loc !== null) {
            echo $loc->getNode()->getEndpoint();
        }

        // 2) Full member view (array with status/weight/host/port...)
        $view = $this->getClusterView();           // array

        // 3) Replicate a local store mutation to peers
        $this->clusterReplicate($actorName, $kind, $payload, $ts);

        // 4) Look up a replicated store entry
        $replica = $this->clusterFindReplica($actorName, $kind); // string|null

        // 5) Actor names that must be failed over from a dead node
        $names = $this->clusterFailoverActors($deadNodeId);      // string[]
    }
}
```

> Design note: these calls may time out / return empty while the `cluster-state` process is still warming up (parallel boot). Callers should tolerate nulls/exceptions (the plugin swallows startup-time IPC errors so boot does not fatal).

---

## 6. Shard routing (ShardRouter)

The routing contract `Yew\Cluster\Router\ShardRouter`:

```php
interface ShardRouter
{
    public function getLocalNode(): ClusterNode;
    public function locate(string $actorName): ?Location;  // locate by actor name
    public function register(string $actorName, Location $location): void;
    public function unregister(string $actorName): void;
}
```

The worker actually receives **`IpcShardRouter`** (inject via the `ShardRouter` interface):

- It rebuilds a consistent-hash ring locally from the member view returned by `cluster-state`;
- Routine lookups use the local cached ring (`locate`/`ownerOf`) and **do not** hit IPC every time;
- The ring is pulled on demand only when it is older than the TTL (`lazyTtlMs`, default 2000ms) or on first lookup;
- Only the `actor-0` process owns the periodic IPC ticker (avoiding N-1 redundant tickers hammering the authority).

```php
use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\IpcShardRouter;

/** @var ShardRouter $router */
$router = DIGet(ShardRouter::class);

$loc = $router->locate('order-123');          // owning node + worker id
$ownerNodeId = $router->ownerOf('order-123'); // just the owning node id

// Fire a rebalance hook when membership changes (e.g. a peer goes DOWN)
if ($router instanceof IpcShardRouter) {
    $router->onRebalance(function (array $changedNodeIds, IpcShardRouter $r) {
        // e.g. evict actors whose owner node was lost
    });
}
```

- **Weight**: virtual points per node = `replicas * weight`, so heavier nodes get more shards.
- **Rebalance**: on membership add/remove or status flip, `refresh()` rebuilds the ring and invokes the `onRebalance` hook (the Actor layer evicts/migrates accordingly).

---

## 7. Cross-node transport (RemoteTransport)

Transport contract `Yew\Cluster\Transport\RemoteTransport` (also implements `Transfer` for fd-level framing):

```php
interface RemoteTransport
{
    public function start(): void;
    public function tell(Location $location, string $method, array $arguments, ?string $traceId): bool;
    public function ask(Location $location, string $method, array $arguments, ?string $traceId, float $timeOut);
    public function create(Location $location, string $className, string $actorName, array $actorData, ?string $parent, ?string $traceId, float $timeOut);
    public function supports(Location $location): bool;
}
```

The worker injects **`PooledTcpRemoteTransport`** (per-node pooled TCP, target host:port taken from the resolved `Location`, decoupled from membership lookups). Inbound traffic is received by the framework-managed `cluster-tcp` multi-port listener and forwarded to this transport.

> Day-to-day business code **does not call** `RemoteTransport` directly: the Actor layer (Proxy + ShardRouter + this transport) transparently completes cross-node calls. `tell` = fire-and-forget; `ask` = request-response (with timeout); `create` = create an actor on its owner node.

---

## 8. Cluster broadcast (ClusterBroadcaster)

```php
interface ClusterBroadcaster
{
    public function broadcast(string $channel, string $message): void;
}
```

`broadcast($channel, $message)` fans the payload out to **every other alive node** in the cluster; the local node is **excluded** (implementations MUST not deliver to local). The implementation is `GossipClusterBroadcaster`. The Multicast module depends only on this interface, so it broadcasts across nodes without coupling to Gossip internals.

```php
use Yew\Cluster\Broadcaster\ClusterBroadcaster;

/** @var ClusterBroadcaster $broadcaster */
$broadcaster = DIGet(ClusterBroadcaster::class);
$broadcaster->broadcast('config-reload', json_encode(['version' => 2]));
```

---

## 9. Security

Gossip messages can be spoof-proofed, in two mutually exclusive modes:

1. **Shared HMAC (default)**: set `secret` (non-empty). All nodes sign with the same key; `clockSkew` bounds message-freshness tolerance (seconds).
2. **Asymmetric per-node signing (stricter)**: set both `privateKey` (this node's private key PEM) and `publicKey` (this node's public key PEM). This switches to per-node signing and, with `trustStore` (`nodeId => publicKey PEM`), enforces a **whitelist**: only nodes whose public key is pinned are admitted.

```php
// Asymmetric + trust store example (under yew.cluster)
'trustStore' => [
    'node-1' => "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
    'node-2' => "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
],
```

---

## 10. Actor replication & failover

- `ClusterActorStore` wraps `FileActorStore` and hooks into replication via `setCluster(ReplicaTransport)`.
- Worker side: `IpcReplicaTransport` — proxies replica mutations over IPC to the `cluster-state` process (single authority, no per-worker drift).
- `cluster-state` side: `LocalReplicaTransport` — persists inbound replicas to disk; on node death, `onNodeDown($deadNodeId)` records the dead node, and workers query `clusterFailoverActors($deadNodeId)` for the actor names to resurrect locally.

```php
// Inside the cluster-state process (framework wires this; shown for semantics)
$state->onNodeDown(function (string $deadNodeId) use ($state) {
    $toResurrect = $state->getFailoverActors($deadNodeId);
    // Workers fetch the same list via clusterFailoverActors() and resurrect locally
});
```

`replicationFactor` controls how many replicas each actor's events/snapshots go to; read quorum = "1 replica present".

---

## 11. Full configuration example (application.yml style)

```yaml
yew:
  cluster:
    enabled: true
    nodeId: node-1
    host: 10.0.0.1
    port: 9501
    weight: 1
    suspectAfter: 3
    downAfter: 8
    heartbeatInterval: 1.0
    gossipHost: 0.0.0.0
    gossipPort: 10501
    gossipBroadcast: 239.0.0.1:49999
    seeds:
      - "10.0.0.2:9501"
      - "10.0.0.3:9501"
    poolSize: 16
    secret: "change-me-shared-hmac"
    clockSkew: 30
    replicas: 128
    replicationFactor: 2
  port:
    cluster-tcp:
      host: 0.0.0.0
      port: 9501
```

Peer nodes only change `nodeId` / `host` / `seeds` to their own values.

---

## 12. FAQ

- **Q: Do I need config for single-machine dev?** No. Default `enabled=false` → `isEnabled()` is `false` → the plugin creates no `cluster-state` process and everything uses local IPC, identical to no-cluster.
- **Q: Why don't workers run Gossip directly?** To avoid a shared cross-worker `Swoole\Table` and split-brain; the authoritative view sits in a single `cluster-state` process, and workers only use a read-only IPC proxy.
- **Q: Does every route lookup hit IPC?** No. `IpcShardRouter` uses a locally cached consistent-hash ring; it pulls from the authority only on first lookup or when the TTL expires.
- **Q: What happens to subscriptions/state when a node dies?** See §10: `onNodeDown` + `clusterFailoverActors()` drive failover and resurrection.
- **Q: How do I swap an implementation?** Use `yew.cluster.services` (state/gossip/router/transport/store) to declaratively override the class and constructor args; constructor args support `%token%` placeholders resolved against the `yew.cluster` subtree.
```
