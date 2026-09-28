# Actor

Yew 提供 Akka 风格的 Actor 模型:以"状态 + 行为"为单元、彼此只通过消息通信的并发实体,
每个 Actor 运行在独立的 `actor` 工作进程中,拥有自己的信箱。Actor 让你获得位置透明的并发、
监督策略,以及在启用 [Cluster](./cluster.md) 后的跨节点透明分发。

返回 [首页](../README.md) · 参见 [Multicast 多播](./multicast.md) · [Cluster 集群](./cluster.md)。

---

## 1. 概述

- 每个 Actor 拥有**信箱**(Swoole Channel / Table),一次只处理一条消息。
- 你通过 **`ActorIpcProxy`** 与 Actor 通信——一个位置透明的句柄。Actor 是在同进程、
  其它 worker、还是其它节点,对你完全透明。
- 三种消息语义:`tell`(发后不理)、`ask`(请求-响应,阻塞)、`askFuture`(请求-响应,异步 Future)。
- 内置**监督**(restart / resume / stop / escalate)、**路由**(round-robin / consistent-hash /
  least-loaded)以及可选**事件溯源**。
- 启用 [Cluster](./cluster.md) 插件后,`ActorIpcProxy` 会透明地把消息路由到其它节点的 Actor。

> Actor **只能**在名字包含 `"actor"` 的进程中创建(`ActorPlugin` 启动 `actor-N` 工作进程)。
> 必须使用 `ActorSystem::create/actorOf`,**不要** `new`。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Actor\ActorPlugin;

$app->addPlugin(new ActorPlugin());
// 若使用 Cluster:ClusterPlugin 必须在 ActorPlugin 之前注册。
```

`ActorPlugin` 会自动拉入 `IpcPlugin`,并约定 `MulticastPlugin` 在它之后
(`MulticastPlugin` 为 `atAfter(ActorPlugin)`)。

---

## 3. 配置

所有键位于 **`yew.actor`**(`ActorConfig`)。

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `maxCount` | int | `10000` | 同时存在的 Actor 上限(共享内存表容量) |
| `maxClassCount` | int | `100` | Actor 类映射表容量 |
| `workerCount` | int | `1` | 启动的 `actor-N` 工作进程数 |
| `mailboxCapacity` | int | `100` | 单个 Actor 信箱(Swoole Channel)容量 |
| `mailboxOverflow` | string | `"block"` | 满时策略:`block` \| `drop` \| `fail` |
| `mailboxPushTimeout` | float | `1.0` | `block` 策略下等待空闲信箱的最长秒数 |
| `supervisorStrategy` | string | `"restart"` | `restart` \| `resume` \| `stop` \| `escalate` |
| `supervisorMaxRetries` | int | `3` | 重试上限,超出转 escalate |
| `supervisorMode` | string | `"one-for-one"` | `one-for-one` \| `all-for-one` |
| `persistenceEnabled` | bool | `false` | 开启事件溯源持久化 |
| `persistenceDir` | string | `/tmp/yew-actor-store` | 文件存储目录 |
| `routingStrategy` | string | `"round-robin"` | `round-robin` \| `consistent-hash` \| `least-loaded` |
| `routingReplicas` | int | `128` | 一致性哈希每节点虚拟副本数 |
| `dispatcher` | string | `"coroutine"` | `coroutine` \| `pinned` \| `thread-pool` |
| `dispatcherPoolSize` | int | `4` | 线程池 worker 数 |
| `telemetryEnabled` | bool | `false` | 每 Actor 指标 / 链路追踪 |

> 集群相关设置(`clusterEnabled`、`clusterNodeId`、`clusterHost`、`clusterPort`、
> `clusterWeight`、`clusterGossipPort`、`clusterSeeds`、`clusterSecret` …)是**从 `yew.cluster` 镜像**而来,
> 不在 `yew.actor` 下配置,请写在 `yew.cluster` 下(见 [Cluster](./cluster.md))。

```yaml
yew:
  actor:
    workerCount: 2
    mailboxCapacity: 10000
    supervisorStrategy: restart
    routingStrategy: consistent-hash
```

---

## 4. 核心 API

### 定义 Actor

```php
abstract class Yew\Plugins\Actor\Actor
{
    abstract protected function handleMessage(ActorMessage $message);
    abstract protected function handleMulticast(ActorMessage $message);
}
```

常用可重写钩子:

- 状态:`initData($data)`、`getData(): array`、`setData(array $data)`。
- 生命周期:`onRestart()`、`preRestart()`、`postStop()`。
- 持久化:`recovery()`、`persist(string $type, $payload)`、`apply(ActorEvent $e)`、
  `takeSnapshot()`、`clearPersisted()`。
- 定时器:`tick(int $msec, callable $cb)`、`after(...)`、`clearTimer($id)`、`clearAllTimer()`。
- 发布/订阅:`subscribe(string $channel)`、`unsubscribe`、`unsubscribeAll`、`hasChannel`、
  `publish(channel, message, excludeActorList=[])`、`publishTo`、`publishIn`。
- 强类型消息:`registerHandler(MessageType $type, callable $handler)`、`handleTyped`。
- 控制:`destroy()`、`getName()`、`getState()`。

### 创建 Actor

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

`Props`(不可变构造器):`Props::create(FooActor::class, $data)`
→ `withName()` · `withParentName()` · `withRoutingKey()` · `withWaitCreate()` ·
`withTimeOut()` · `withPinLocal()`。

### 发送消息(通过 `ActorIpcProxy`)

```php
$proxy->tell(string $method, array $arguments = []): bool;          // 发后不理
$proxy->ask(string $method, array $arguments = [], float $timeOut = 0);  // 阻塞取返回值
$proxy->askFuture(string $method, array $arguments = [], float $timeOut = 0): ActorFuture;
$proxy->__call(string $method, array $arguments);                   // 魔法调用,非 oneway ask
$proxy->isRemote(): bool;
```

`ActorFuture`:`await(float $timeout = 0)`、`then(callable $onFulfilled)`、`isComplete()`、
`resolve($v)`、`reject(\Throwable $e)`。

> 生命周期控制(`destroy` / `stop` / …)**禁止**经 IPC 调用——务必使用
> `ActorSystem::destroy($actorName)`。

---

## 5. 使用示例

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
        return $this->data['count'];   // 返回值即 ask() 的结果
    }

    protected function handleMulticast(ActorMessage $message)
    {
        // 收到 TYPE_MULTICAST 消息(见 Multicast)
    }
}

// 创建(返回 IPC 代理)
/** @var \Yew\Plugins\Actor\ActorIpcProxy $proxy */
$proxy = ActorSystem::actorOf(
    CounterActor::class,
    Props::create(CounterActor::class)->withName('counter-1')->withData(['count' => 0])
);

$proxy->tell('handleMessage', [$someData]);                  // 发后不理
$count = $proxy->ask('handleMessage', [$someData]);          // 阻塞取结果
$future = $proxy->askFuture('handleMessage', [$someData]);
$count = $future->await(5.0);                                // 异步
```

---

## 6. 依赖与插件顺序

- **IpcPlugin** —— 必需,由 `ActorPlugin` 自动加入(`atAfter(IpcPlugin::class)`)。
- **ClusterPlugin** —— 可选;若 `yew.cluster.enabled`,`ActorPlugin` 会注入真实的
  `ShardRouter` / `RemoteTransport`,使代理变为 `isRemote()`。
- 顺序:`Ipc` → `Actor` → `Multicast`。

---

## 7. 注意事项

- 只能在 `actor` 进程内创建 Actor,切勿 `new`。
- 生命周期方法请用 `ActorSystem::destroy`,不要走 IPC。
- 启用集群后,默认一致性哈希路由;节点上下线时 `ShardRouter` 会再平衡,可能驱逐不再归属本地的 Actor。
- `@Value` 配置变量**不会**从 YAML 自动注册(见 [快速开始](./getting-started.md) §7)。

---

## 8. 相关文档

- [Multicast 多播](./multicast.md) —— Actor 间基于主题的发布/订阅
- [Cluster 集群](./cluster.md) —— 跨节点 Actor 传输与故障转移
- [快速开始](./getting-started.md) —— 配置、插件生命周期、DI
