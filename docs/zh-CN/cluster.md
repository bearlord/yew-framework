# Yew Cluster 集群组件使用文档（中文）

> 适用版本：Yew 框架 `Yew\Cluster` 包（位于 `vendor/bearlord/yew-framework/src/Cluster`）
> 关键词：Gossip 成员管理、一致性哈希分片路由、跨节点 Actor 传输、集群广播、Actor 副本与故障转移

---

## 1. 概述

`Yew\Cluster` 是 Yew 框架的集群能力包，为 Actor 层提供**位置透明**的分布式原语：

- **成员管理（Membership）**：基于 Gossip 协议（SYN/SYN-ACK/ACK 握手、摘要反熵、UDP 分片与重传、故障检测 FD）维护集群节点视图。
- **分片路由（Shard Router）**：用一致性哈希把 Actor 名映射到所属节点，支撑按 key 自动分片与再平衡。
- **跨节点传输（Remote Transport）**：跨节点的 Actor `tell` / `ask` / `create`（等价于 Akka remoting / Orleans silo-to-silo）。
- **集群广播（Broadcaster）**：把消息扇出到集群中其他所有存活节点。
- **Actor 副本与故障转移**：把 Actor 的事件/快照复制到副本节点，节点宕机后由存活节点复活其 Actor。

### 架构要点：单一权威进程

集群状态**只**存在于每个节点上的 `cluster-state` 辅助进程（helper process）。Worker 进程通过 IPC（`GetClusterState`）只读地查询它，因此：

- 所有 Worker 看到完全一致的成员视图与分片环，**不会出现 per-worker 分歧或脑裂**；
- 不再依赖跨 Worker 的共享 `Swoole\Table`；
- Gossip UDP 套接字由 `cluster-state` 进程自管理（`UdpGossipTransport`，`setManaged(false)`），只有它收发 Gossip 流量。

```
        +-------------------+        IPC (GetClusterState)        +----------------------+
        |  cluster-state    | <---------------------------------- |   worker / actor     |
        |  (权威成员视图 +   |    getMemberView / getLocationArray  |   进程 (只读代理)      |
        |   FD + 分片环 +    |    replicateStoreEntry / findReplica |  IpcShardRouter       |
        |   Gossip 引擎)     |    getFailoverActors                 |  PooledTcpRemoteTransport |
        +-------------------+                                      +----------------------+
                  |  UDP Gossip (239.0.0.1:49999 或指定广播)
                  v
           其他节点的 cluster-state 进程
```

---

## 2. 启用插件

`ClusterPlugin` 自动依赖 `IpcPlugin`（在它之后启动），并**必须早于** `ActorPlugin` 启动（因为 `ActorPlugin` 在 `beforeServerStart` 通过 `DIGet(ShardRouter::class)` 解析路由，需要 `ClusterPlugin` 先注册具体的 `IpcShardRouter`）。

```php
use Yew\Cluster\ClusterPlugin;
use Yew\Plugins\Actor\ActorPlugin;

// 在你的 Server 引导处注册：
$server->getPlugManager()->addPlug(new ClusterPlugin());
$server->getPlugManager()->addPlug(new ActorPlugin());
```

若不使用 Actor 而只想用集群原语，`ClusterPlugin` 也可独立加载（它会自行声明对 `IpcPlugin` 的依赖）。

> 注意：集群默认是**关闭**的。只有在配置中 `yew.cluster.enabled = true` 且 `beforeServerStart` 通过校验后，才会创建 `cluster-state` 进程并装配跨节点传输。未启用时，`ClusterConfig::isEnabled()` 返回 `false`，插件直接 return，行为与单机一致。

---

## 3. 配置

集群配置统一来自 `yew.cluster` 子树（`Yew\Cluster\ClusterConfig`，键名 `KEY = "cluster"`）。可用字段如下：

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `enabled` | bool | `false` | 是否开启集群（分片 + Gossip）。 |
| `nodeId` | string | `node-<hostname>` | 本节点稳定标识，集群内唯一。 |
| `host` | string | `127.0.0.1` | 通告给对等节点的绑定主机。 |
| `port` | int | `0` | 通告给对等节点的绑定端口。 |
| `weight` | int | `1` | 节点权重，越高承担越多分片。 |
| `suspectAfter` | int | `3` | 心跳缺失多少秒后标记为 *suspect*。 |
| `downAfter` | int | `8` | 心跳缺失多少秒后标记为 *down*。 |
| `heartbeatInterval` | float | `1.0` | 成员心跳/故障检测周期（秒）。 |
| `gossipHost` | string | `0.0.0.0` | Gossip UDP 绑定地址。 |
| `gossipPort` | int | `port + 1000` | Gossip UDP 端口（默认 host 端口 +1000）。 |
| `gossipBroadcast` | string | `239.0.0.1:49999` | Gossip 广播/组播目标（`host:port`）。 |
| `seeds` | string[] | `[]` | 首次联系的种子节点（`host:port` 列表）。 |
| `poolSize` | int | `16` | 跨节点 TCP 连接池大小（每节点）。 |
| `secret` | string | `''` | Gossip 消息共享 HMAC 密钥（防伪造）。 |
| `clockSkew` | int | `30` | Gossip 消息允许的时间偏移（秒）。 |
| `privateKey` | string | `''` | 本节点私钥 PEM；设置后切换为**非对称**逐节点签名。 |
| `publicKey` | string | `''` | 本节点公钥 PEM（与 `privateKey` 配对）。 |
| `trustStore` | array | `[]` | 信任库：`nodeId => 公钥PEM`；非空时只接受已钉住公钥的节点。 |
| `replicationFactor` | int | `2` | Actor 事件/快照复制到的副本数（除拥有节点外）。 |
| `replicas` | int | `128` | 一致性哈希环上每节点的虚拟副本点（越大越均衡）。 |
| `services` | array | `[]` | 声明式服务覆盖：`state` / `gossip` / `router` / `transport` / `store`。 |

另外，`yew.cluster.peers`（`nodeId => "host:port"`）可用于显式指定对等节点静态映射；若省略，则按 `seeds` 生成合成 id。

跨节点入站 TCP 端口需在 `yew.port` 下声明，名字固定为 **`cluster-tcp`**：

```yaml
yew:
  port:
    cluster-tcp:
      host: 0.0.0.0
      port: 9501
```

---

## 4. 核心概念

### ClusterNode（节点描述符）
```php
$node = new ClusterNode($nodeId, $host, $port, $local);
$node->getNodeId();   // 稳定节点 id
$node->getHost();     // 主机/ip
$node->getPort();     // 监听端口（0 = 不可网络可达）
$node->isLocal();     // 是否为本进程所属节点
$node->getEndpoint(); // 例如 "node-1@10.0.0.1:9501"
```

### Location（Actor 物理位置）
`Location` 是“Actor X 在哪里”的唯一真相源，使寻址位置透明：
```php
$location->getNode();       // 所属 ClusterNode
$location->getProcessId();  // 承载该 Actor 的 worker 进程 id
$location->getActorName();  // 可选的 Actor 名
$location->isLocal();       // 是否在本地节点
```

### 成员状态
成员视图（`getMemberView()`）中每个节点带有 `status` 字段，典型值：`UP`、`SUSPECT`、`DOWN`。`DOWN` 会触发再平衡与故障转移候选。

---

## 5. 查询集群状态（GetClusterState）

在任意 Actor / Worker 中 `use Yew\Cluster\GetClusterState;` 即可获得一组便捷方法，它们通过 IPC 转发到 `cluster-state` 进程：

```php
use Yew\Cluster\GetClusterState;

class MyActor
{
    use GetClusterState;

    public function demo(): void
    {
        // 1) 解析某个 Actor 的归属节点（位置透明）
        $loc = $this->clusterLocate('my-actor');   // Location|null
        if ($loc !== null) {
            echo $loc->getNode()->getEndpoint();
        }

        // 2) 获取完整成员视图（数组，含 status/weight/host/port...）
        $view = $this->getClusterView();           // array

        // 3) 把本地存储变更复制给对等节点
        $this->clusterReplicate($actorName, $kind, $payload, $ts);

        // 4) 查找某个副本条目
        $replica = $this->clusterFindReplica($actorName, $kind); // string|null

        // 5) 获取某宕机节点上、需要故障转移的 Actor 名列表
        $names = $this->clusterFailoverActors($deadNodeId);      // string[]
    }
}
```

> 设计要点：这些方法在 `cluster-state` 进程尚未就绪（并行启动期间）可能超时/返回空，调用方应做空值/异常容错（插件侧已吞掉启动期 IPC 异常，不会 fatal）。

---

## 6. 分片路由（ShardRouter）

路由接口 `Yew\Cluster\Router\ShardRouter`：

```php
interface ShardRouter
{
    public function getLocalNode(): ClusterNode;
    public function locate(string $actorName): ?Location;  // 由 actor 名定位
    public function register(string $actorName, Location $location): void;
    public function unregister(string $actorName): void;
}
```

Worker 端实际拿到的是 **`IpcShardRouter`**（通过 DI 注入，类型提示用接口 `ShardRouter`）：

- 它在本地用 `cluster-state` 返回的成员视图**重建一致性哈希环**；
- 普通路由查找走本地缓存环（`locate`/`ownerOf`），**不**每次打 IPC；
- 当本地环超过 TTL（`lazyTtlMs`，默认 2000ms）或首次查找时，才按需向 `cluster-state` 拉取最新视图；
- 只有 `actor-0` 进程持有周期性 IPC 定时器（避免 N-1 个冗余定时器同时打权威进程）。

```php
use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\IpcShardRouter;

/** @var ShardRouter $router */
$router = DIGet(ShardRouter::class);

$loc = $router->locate('order-123');          // 归属节点 + worker id
$ownerNodeId = $router->ownerOf('order-123'); // 仅返回归属节点 id

// 成员视图变化（如某 peer DOWN）时触发再平衡钩子
if ($router instanceof IpcShardRouter) {
    $router->onRebalance(function (array $changedNodeIds, IpcShardRouter $r) {
        // 例如驱逐归属已丢失节点的 Actor
    });
}
```

- **权重**：环上每个节点的虚拟点数 = `replicas * weight`，因此高权重节点分得更多分片。
- **再平衡**：成员增删或状态翻转时，`refresh()` 重建环并通过 `onRebalance` 钩子通知上层（Actor 层据此驱逐/迁移）。

---

## 7. 跨节点消息传输（RemoteTransport）

传输接口 `Yew\Cluster\Transport\RemoteTransport`（同时实现 `Transfer` 以支持 fd 级分帧）：

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

Worker 端注入的是 **`PooledTcpRemoteTransport`**（连接池按目标节点 host:port 复用，目标地址来自解析出的 `Location`，与 Gossip 成员查找解耦）。入站由框架托管的 `cluster-tcp` 多端口监听器接收并转发给该 transport。

> 日常业务**不需要直接调用** `RemoteTransport`：Actor 层（Proxy + ShardRouter + 该 transport）会自动把跨节点调用透明地完成。`tell` = 发后不理；`ask` = 请求-响应（带超时）；`create` = 在归属节点创建 Actor。

---

## 8. 集群广播（ClusterBroadcaster）

```php
interface ClusterBroadcaster
{
    public function broadcast(string $channel, string $message): void;
}
```

`broadcast($channel, $message)` 把负载扇出到集群中**其他所有存活节点**；本地节点**已被排除**（实现必须不投送给本地）。实现类为 `GossipClusterBroadcaster`。Multicast 模块只依赖该接口，因此可跨节点广播而不耦合 Gossip 内部。

```php
use Yew\Cluster\Broadcaster\ClusterBroadcaster;

/** @var ClusterBroadcaster $broadcaster */
$broadcaster = DIGet(ClusterBroadcaster::class);
$broadcaster->broadcast('config-reload', json_encode(['version' => 2]));
```

---

## 9. 安全

Gossip 消息可防伪造，两种方式二选一：

1. **共享 HMAC（默认）**：设置 `secret`（非空）。所有节点使用同一密钥签名，`clockSkew` 控制消息新鲜度容差（秒）。
2. **非对称逐节点签名（更严格）**：同时设置 `privateKey`（本节点私钥 PEM）与 `publicKey`（本节点公钥 PEM）。此时切换为逐节点签名，并可配合 `trustStore`（`nodeId => 公钥PEM`）实现**白名单**：仅接受公钥已钉住的节点加入。

```php
// 非对称 + 信任库示例（在 yew.cluster 中）
'trustStore' => [
    'node-1' => "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
    'node-2' => "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
],
```

---

## 10. Actor 副本与故障转移

- `ClusterActorStore` 包装 `FileActorStore`，通过 `setCluster(ReplicaTransport)` 接入复制通道。
- Worker 端：`IpcReplicaTransport` —— 把副本变更经 IPC 代理到 `cluster-state` 进程（单一权威，避免 per-worker 漂移）。
- `cluster-state` 端：`LocalReplicaTransport` —— 直接把入站副本落盘；节点宕机时 `onNodeDown($deadNodeId)` 记录死者，Worker 通过 `clusterFailoverActors($deadNodeId)` 查询应在本节点复活的 Actor 名。

```php
// cluster-state 进程内（框架已装配；此处仅展示语义）
$state->onNodeDown(function (string $deadNodeId) use ($state) {
    $toResurrect = $state->getFailoverActors($deadNodeId);
    // Worker 通过 clusterFailoverActors() 取同一列表并在本地复活
});
```

`replicationFactor` 控制每个 Actor 的事件/快照复制到几个副本；读取法定人数 = “存在 1 个副本”。

---

## 11. 完整配置示例（application.yml 风格）

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

对等节点只需把 `nodeId`/`host`/`seeds` 改成各自的值即可。

---

## 12. 常见问题

- **Q：单机开发也要配置吗？** 不需要。默认 `enabled=false`，`ClusterConfig::isEnabled()` 为 `false`，插件不创建 `cluster-state` 进程，全部走本地 IPC，行为与无集群一致。
- **Q：为什么 Worker 不直接跑 Gossip？** 为了避免多 Worker 共享 `Swoole\Table` 与脑裂；权威视图集中在单一 `cluster-state` 进程，Worker 仅通过 IPC 只读代理。
- **Q：路由查找会每次打 IPC 吗？** 不会。`IpcShardRouter` 走本地缓存的一致性哈希环；仅在首次查找或 TTL 过期时才向权威进程拉取。
- **Q：节点宕机后订阅/状态怎么办？** 见第 10 节：`onNodeDown` + `clusterFailoverActors()` 驱动故障转移与复活。
- **Q：如何自定义实现？** 通过 `yew.cluster.services`（state/gossip/router/transport/store）声明式覆盖类名与构造参数，构造参数支持 `%token%` 占位符（从 `yew.cluster` 子树解析）。
```
