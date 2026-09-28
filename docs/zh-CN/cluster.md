# Yew Cluster 集群

Cluster 包为 Actor 层提供位置透明的分布式原语:Gossip 成员管理、一致性哈希分片路由、跨节点
Actor 传输、集群广播,以及 Actor 副本与故障转移。一个权威的 `cluster-state` 辅助进程持有成员视图;
Worker 通过 IPC **只读**查询它,因此无脑裂、无共享 `Swoole\Table`。

返回 [首页](../README.md) · 参见 [Actor](./actor.md) · [Multicast 多播](./multicast.md)。

---

## 1. 概述

- **成员管理** —— `GossipClusterState` 通过 UDP Gossip(SYN/SYN-ACK/ACK 握手、摘要反熵、UDP 分片与重传、
  故障检测 FD)维护集群视图。
- **分片路由** —— 一致性哈希环把 Actor 名映射到归属节点;拓扑变化时再平衡。
- **跨节点传输** —— `PooledTcpRemoteTransport` 实现 `tell`/`ask`/`create`(等价于 Akka remoting /
  Orleans silo-to-silo)。
- **集群广播** —— `ClusterBroadcaster` 把消息扇出到其它所有存活节点(`GossipClusterBroadcaster`)。
- **副本与故障转移** —— 复制 Actor 事件/快照;节点宕机后存活节点复活其 Actor。

### 架构:单一权威进程

集群状态**只**存在于每个节点的 `cluster-state` 辅助进程(每节点一个)。Worker 进程通过 IPC
(`GetClusterState`)对其做**只读**查询,因此:

- 每个 Worker 看到的成员视图与分片环完全一致 —— **无脑裂、无分歧**;
- 不使用跨 Worker 共享的 `Swoole\Table`;
- Gossip UDP 套接字在 `cluster-state` 进程内打开(`UdpGossipTransport`,`setManaged(false)`),
  只有它收发 Gossip 流量。

```
        +----------------------+       IPC(GetClusterState)        +----------------------+
        |   cluster-state      | <-------------------------------- |   worker / actor     |
        |   (权威视图+FD+分片环  |   getMemberView/getLocationArray    |   (只读)              |
        |    +Gossip+TCP)      |   replicateStoreEntry/findReplica  |   IpcShardRouter      |
        |                      |   getFailoverActors               |   PooledTcpRemote...  |
        +----------------------+                                    +----------------------+
                  |  UDP Gossip(如 239.0.0.1:49999 或配置的广播地址)
                  v
           其它节点的 cluster-state 进程
```

---

## 2. 启用集群

必须注册 `ClusterPlugin`,且**在 `ActorPlugin` 之前**(它注册 `ActorPlugin` 在 `beforeServerStart`
中解析的具体 `IpcShardRouter`):

```php
use Yew\Cluster\ClusterPlugin;
use Yew\Plugins\Actor\ActorPlugin;

// 在服务启动引导中:
$server->getPlugManager()->addPlug(new ClusterPlugin());
$server->getPlugManager()->addPlug(new ActorPlugin());
```

若只需集群原语(不用 Actor),`ClusterPlugin` 也可独立加载(它自行声明对 `IpcPlugin` 的依赖)。

> 集群**默认关闭**(`ClusterConfig::isEnabled()` 为 `false`)。仅当 `yew.cluster.enabled = true`
> 且 `beforeServerStart` 校验通过才会激活;否则插件提前返回,行为与单机一致。

---

## 3. 配置

所有键位于 **`yew.cluster`**(`ClusterConfig`,`KEY = "cluster"`)。

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `enabled` | bool | `false` | 开启集群(分片 + Gossip) |
| `nodeId` | string | `node-<hostname>` | 集群内稳定的唯一节点标识 |
| `host` | string | `127.0.0.1` | 通告给对等节点的主机 |
| `port` | int | `0` | 通告的业务/跨节点 TCP 端口 |
| `weight` | int | `1` | 节点权重(越高承担越多分片) |
| `suspectAfter` | int | `3` | 心跳缺失多少秒后标记为 SUSPECT |
| `downAfter` | int | `8` | 心跳缺失多少秒后标记为 DOWN |
| `heartbeatInterval` | float | `1.0` | 心跳/故障检测周期(秒) |
| `gossipHost` | string | `0.0.0.0` | Gossip UDP 绑定地址 |
| `gossipPort` | int | `0`(`port + 1000`) | Gossip UDP 端口 |
| `gossipBroadcast` | string | `239.0.0.1:49999` | Gossip 广播/组播目标 `host:port` |
| `seeds` | string[] | `[]` | 种子节点(`host:port`) |
| `poolSize` | int | `16` | 跨节点 TCP 连接池大小 |
| `secret` | string | `""` | 共享 HMAC 密钥(非空即开启签名) |
| `clockSkew` | int | `30` | Gossip 消息时间窗口(秒,防重放) |
| `privateKey` | string | `""` | 节点私钥 PEM(非对称模式,需与 `publicKey` 同时设置) |
| `publicKey` | string | `""` | 节点公钥 PEM |
| `trustStore` | array | `[]` | `nodeId => 公钥PEM` 白名单 |
| `replicationFactor` | int | `2` | 每个 Actor 复制的副本数(除归属节点外) |
| `replicas` | int | `128` | 一致性哈希每节点虚拟副本数 |
| `services` | array | `[]` | 声明式覆盖(state/gossip/router/transport/store) |
| `cluster-tcp` 端口 | — | — | 跨节点入站 TCP,名字**必须**为 `cluster-tcp`(在 `yew.port` 下) |

`yew.cluster.peers`(`nodeId => "host:port"`)可静态指定对等节点映射;若省略,则从 `seeds` 派生合成 id。

跨节点入站 TCP 端口必须在 `yew.port` 下以固定名字 **`cluster-tcp`**(`ClusterTcpPort::NAME`)声明:

```yaml
yew:
  port:
    cluster-tcp:                      # 名字必须恰好是 "cluster-tcp"
      host: 0.0.0.0
      port: 9501
```

> 框架会绑定 `cluster-tcp` 套接字(Swoole 多端口)并把入站连接转发给 `PooledTcpRemoteTransport`。
> 若未声明该端口,会记录 warning,跨节点**入站** Actor 调用将无法服务(出站仍可用)。

---

## 4. 成员视图与发现

在任意 Actor / Worker 中使用 `GetClusterState` trait:

```php
use Yew\Cluster\GetClusterState;

class MyActor
{
    use GetClusterState;

    public function demo(): void
    {
        $loc = $this->clusterLocate('order-123');      // Location|null
        if ($loc !== null) {
            echo $loc->getNode()->getEndpoint();        // "node-1@10.0.0.1:9501"
        }
        $view = $this->getClusterView();                // 成员数组
    }
}
```

| 方法 | 返回 | 说明 |
|------|------|------|
| `clusterLocate(string $actorName)` | `Location\|null` | 某 Actor 的归属节点/位置 |
| `getClusterView()` | `array` | 完整成员视图(`nodeId/host/port/local/status/...`) |
| `clusterReplicate($actorName, $kind, $payload, $ts)` | `void` | 把本地存储条目复制给对等节点 |
| `clusterFindReplica($actorName, $kind)` | `string\|null` | 查找副本条目 |
| `clusterFailoverActors($ownerNodeId)` | `string[]` | 节点宕机后需复活的 Actor 名 |

> 这些方法在并行启动期可能超时/返回空(框架已吞掉启动期 IPC 异常),调用方应做空值容错。

`ClusterNode`(描述符类,**没有** `GetClusterNode` trait):`getNodeId()` / `getHost()` / `getPort()` /
`isLocal()` / `getEndpoint()`。`Location`:`getNode()` / `getProcessId()` / `getActorName()` / `isLocal()`。

---

## 5. 分片路由(ShardRouter)

路由契约 `Yew\Cluster\Router\ShardRouter`:

```php
interface ShardRouter
{
    public function getLocalNode(): ClusterNode;
    public function locate(string $actorName): ?Location;  // 按 Actor 名定位
    public function register(string $actorName, Location $location): void;
    public function unregister(string $actorName): void;
}
```

Worker 实际注入的是 **`IpcShardRouter`**(通过 `ShardRouter` 接口注入):

- 它从 `cluster-state` 返回的成员视图在本地重建一致性哈希环;
- 常规查找使用本地缓存的环(`locate`/`ownerOf`),**不会**每次都走 IPC;
- 仅当环比 TTL(`lazyTtlMs`,默认 2000ms)更旧或首次查找时才按需拉取;
- 只有 `actor-0` 进程持有周期性 IPC ticker(避免 N-1 个冗余 ticker 同时冲击权威进程)。

```php
use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\IpcShardRouter;

/** @var ShardRouter $router */
$router = DIGet(ShardRouter::class);

$loc = $router->locate('order-123');          // 归属节点 + worker id
$ownerNodeId = $router->ownerOf('order-123'); // 仅归属节点 id
$view = $router->getView();                   // 最近一次 refresh 缓存的成员视图

// 成员变化时(如某 peer 变为 DOWN)触发再平衡钩子
if ($router instanceof IpcShardRouter) {
    $router->onRebalance(function (array $changedNodeIds, IpcShardRouter $r) {
        // 例如:驱逐归属节点已丢失的 Actor
    });
}
```

- **权重**:每节点虚拟副本数 = `replicas * weight`(取整,最小 1),权重越高分片越多。
- **再平衡**:成员增删或状态翻转时,`refresh()` 重建环并调用 `onRebalance` 钩子(Actor 层据此驱逐/迁移)。
- `register()` / `unregister()` 在 `IpcShardRouter` 上**故意为空**(归属由权威环在查找时推导)。

---

## 6. 跨节点 Actor 传输

`PooledTcpRemoteTransport`(注入到 Worker)提供 `tell` / `ask` / `create`。日常业务中**你不会
直接调用它** —— Actor 层(代理 + `ShardRouter` + 该 transport)会透明完成:`PooledTcpRemoteTransport`
按目标 `Location` 解析并复用每节点的 TCP 连接池。入站流量到达 `cluster-tcp` 端口。

`RemoteTransport` 接口方法:`start()` / `tell(Location,method,args,traceId)` / `ask(Location,method,args,traceId,timeout)`
/ `create(Location,className,actorName,actorData,parent,traceId,timeout)` / `supports(Location)`。

---

## 7. 集群广播

```php
use Yew\Cluster\Broadcaster\ClusterBroadcaster;
use Yew\Cluster\Broadcaster\GossipClusterBroadcaster;

/** @var ClusterBroadcaster $bc */
$bc = DIGet(ClusterBroadcaster::class);
$bc->broadcast('config-reload', json_encode(['version' => 2]));   // 自动排除本节点

// 接收端把入站帧解析回 [channel, message]:
$parsed = GossipClusterBroadcaster::parse($payload); // ['channel'=>...,'message'=>...] | null
```

`GossipClusterBroadcaster` 使用**独立**的 UDP 传输(与内部 gossip 通道分离,因此多播帧绝不会被误认为
SYNC/SYN-ACK gossip 帧),线格式为 `{"type":"mc","channel":...,"message":...}`,`parse()` 对非多播帧返回 `null`。
Multicast 模块只依赖该接口。

---

## 8. 安全

Gossip 消息可防伪造,两种互斥模式:

- **共享 HMAC**(默认兼容模式):`secret` 非空 → 对所有出站消息做 `hash_hmac('sha256', …)`;
  `clockSkew` 控制新鲜度窗口(防重放)。
- **非对称**(更严格):同时设置 `privateKey` + `publicKey` → 逐节点签名;公钥 + `keyId` 随消息携带,接收方验签。
  `trustStore`(非空)即**白名单**:只接受公钥已钉住的节点,未知/伪造公钥直接丢弃。

> `privateKey`/`publicKey` 优先于 `secret`;三者皆空 → 关闭签名。

```php
// 非对称 + 信任库示例(位于 yew.cluster 下)
'trustStore' => [
    'node-1' => "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
    'node-2' => "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
],
```

---

## 9. 副本与故障转移

- `ClusterActorStore` 包装 `FileActorStore`,经 `ReplicaTransport` 复制。
- Worker 端:`IpcReplicaTransport` 将副本转发到 `cluster-state`。
- `cluster-state` 端:`LocalReplicaTransport` 落盘;某对等节点 DOWN 时触发 `onNodeDown`,
  Worker 通过 `clusterFailoverActors($deadNodeId)` 复活 Actor。

---

## 10. 注意事项

- `cluster-tcp` 入站端口的名字必须恰好是 `cluster-tcp`(`ClusterPlugin::wirePorts()` 据此查找监听器)。
- 没有 `GetClusterNode` trait;请使用 `ClusterNode` 类。
- 启动期间 `GetClusterState` 调用可能超时,务必做空值检查。

---

## 11. 相关文档

- [Actor](./actor.md) · [Multicast 多播](./multicast.md) · [快速开始](./getting-started.md)
