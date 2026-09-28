# Multicast 多播(发布/订阅)

Multicast 在 Actor 之间提供基于主题的发布/订阅。订阅关系保存在独立的 `multicast` 辅助进程中
(底层为跨进程 Swoole Table),消息还可选择性地通过 UDP/Gossip **扇出到其它集群节点**。主题支持
MQTT 风格的通配符 `+`(单层)与 `#`(多层)。

返回 [首页](../README.md) · 参见 [Actor](./actor.md) · [Cluster 集群](./cluster.md)。

---

## 1. 概述

- 在 **Actor 内部**使用:在 `$this`(一个 `Actor`)上调用 `subscribe()` / `publish()`。
- 通配符:`+` 精确匹配一层;`#` 匹配主题树的剩余部分。
- 投递给订阅者的消息以 `TYPE_MULTICAST` 类型的 `ActorMessage` 到达,Actor 在
  `handleMulticast()` 中处理。
- 可选的跨节点扇出需要 [Cluster](./cluster.md) 插件。

---

## 2. 安装与插件注册

`MulticastPlugin` 通常由 `ActorPlugin` 自动拉入(`atAfter(ActorPlugin)`),因此通常只需注册
`ActorPlugin`。显式写法:

```php
use Yew\Plugins\Actor\ActorPlugin;
use Yew\Plugins\Actor\Multicast\MulticastPlugin;

$app->addPlugin(new ActorPlugin());
$app->addPlugin(new MulticastPlugin());
```

---

## 3. 配置

键位于 **`yew.multicast`**(`MulticastConfig`)。

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `clusterEnabled` | bool | `false` | 是否同时把消息扇出到其它集群节点 |
| `clusterPort` | int | `0` | 集群多播 UDP 端口;`0` = `gossipPort + 1`;`>0` 才启用扇出 |
| `broadcaster` | `ClusterBroadcaster\|null` | `null` | 由插件注入,通常不必设置 |
| `cacheChannelCount` | int | `10000` | 跨进程订阅表(Swoole Table)频道行容量 |
| `channelMaxLength` | int | `256` | 频道名最大长度 |
| `cacheActorCount` | int | `10000` | 订阅表 actor 行容量 |
| `actorMaxLength` | int | `256` | actor 名最大长度 |
| `processName` | string | `"multicast"` | 辅助进程名 |

> 内部 Swoole Channel 容量取自 `yew.actor` 下的 `actor.multicastChannelCapacity`(默认 `10000`)。

```yaml
yew:
  multicast:
    clusterEnabled: true
    clusterPort: 50000   # 必须与 cluster.gossipPort 区分且 > 0
```

---

## 4. 核心 API

### 在 Actor 内部

```php
// Yew\Plugins\Actor\Actor(委托给 Multicast)
public function subscribe(string $channel): void;
public function unsubscribe(string $channel): void;
public function unsubscribeAll(): void;
public function hasChannel(string $channel): bool;
public function publish(string $channel, string $message, array $excludeActorList = []): void; // 排除自己
public function publishTo(string $channel, string $message): void;   // 仅他人
public function publishIn(string $channel, string $message): void;   // 含自己
```

### 在 Actor 外部(使用 `GetMulticast` trait)

```php
use Yew\Plugins\Actor\Multicast\GetMulticast;

$dispatcher->actorSubscribe(string $channel, string $actor): void;
$dispatcher->actorUnsubscribe(string $channel, string $actor): void;
$dispatcher->actorUnsubscribeAll(string $actor): void;
$dispatcher->actorHasChannel(string $channel, string $actor): bool;
$dispatcher->actorPublish(string $channel, ?string $message, array $excludeActorList = []): void;
$dispatcher->deleteChannel(string $channel): void;
```

### 通配符

- `+` —— 单层,必须独占一层(`a/+/c` 合法;`a/b+/c` 非法)。
- `#` —— 多层,必须是整个过滤器的最后字符(`a/#` 合法;`a/#/c` 非法)。
- 频道长度 `1..256`;`$` 开头的系统主题有特殊处理。

---

## 5. 使用示例

```php
use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorMessage;

class ChatActor extends Actor
{
    protected function handleMessage(ActorMessage $message)
    {
        $this->subscribe('room/+/chat');                       // 订阅
        $this->publish('room/42/chat', json_encode(['u' => 'alice', 't' => 'hi']));
    }

    protected function handleMulticast(ActorMessage $message)
    {
        $payload = $message->getData();   // ['channel' => ..., 'type' => 'multicast', 'message' => ...]
        // $payload['message'] 即发布的内容
    }
}
```

在控制器 / worker(Actor 外部上下文)通过 trait:

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

## 6. 依赖与插件顺序

- `MulticastPlugin` 为 `atAfter(ActorPlugin::class)`。
- 仅当 `clusterEnabled` **且** 存在 `GossipClusterState` **且** `clusterPort > 0` 时,
  才会接线集群扇出。它通过 `GetIpc` 调用 `multicast` 进程。
- 若集群未启用,插件打印警告并降级为仅本地投递。

---

## 7. 注意事项

- 订阅关系保存在跨进程表中,worker 重启不会丢失。
- 跨节点投递使用的 UDP 端口**独立于**集群 gossip 端口——务必区分开。
- `publish()` 默认排除发布者自身;要在本地也收到请用 `publishIn()`。

---

## 8. 相关文档

- [Actor](./actor.md) · [Cluster 集群](./cluster.md) · [快速开始](./getting-started.md)
