# MQTT

Yew 提供一流的 MQTT 3.1.1 / 5.0 服务端支持:报文解析、带 `+`/`#` 通配符的订阅树、独立的
`mqtt-connection` 辅助进程保存连接状态,以及在启用 [Cluster](./cluster.md) 插件时**可选的跨节点投递**。

返回 [首页](../README.md) · 参见 [Route 路由](./route.md) · [Pack 打包](./pack.md) ·
[Cluster 集群](./cluster.md)。

---

## 1. 概述

- `MqttPlugin` 负责报文解析、路由、订阅索引与本地投递。
- MQTT 协议状态机(CONNACK / SUBACK / PUBACK / Will)由**你的**应用 MQTT 控制器实现
  (使用 `@MqttController` / `@MqttMapping` 注解)。
- `MqttConnectionPlugin` 运行一个 `mqtt-connection` 辅助进程,保存 `fd ↔ clientId`、Will 消息与
  keep-alive 定时器,并**可选**地把 PUBLISH 扇出到其它节点。

> MQTT 打包工具是 `Yew\Plugins\Mqtt\MqttPack`。注意:**不存在** `WsJsonPack` 类;可用的打包工具为
> `JsonPack`、`NonJsonPack`、`LenJsonPack`、`EofJsonPack`、`StreamPack`、`MqttPack`。WS 约定动作键为
> `"p"`;`JsonPack` 使用 `"action"`。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Mqtt\MqttPlugin;
use Yew\Plugins\Mqtt\Connection\MqttConnectionPlugin;

$app->addPlugin(new MqttPlugin());
$app->addPlugin(new MqttConnectionPlugin());
```

`MqttPlugin` 自动加入 `UidPlugin`、`TopicPlugin`、`PackPlugin`。`MqttConnectionPlugin` 依赖
`PackPlugin`(`autoBoostSend`);跨节点投递还需 [Cluster](./cluster.md) 插件的 `GossipClusterState`。

---

## 3. 配置

### `yew.mqtt`(`MqttPluginConfig`)

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `allowAnonymousAccess` | bool | `true` | 允许匿名连接 |
| `serverQos` | int | `0` | 下发消息给客户端时使用的 QoS |
| `useRoute` | bool | `false` | 把 topic 当作路由 path(不再走 MQTT 语义) |
| `serverTopic` | string | `""` | `useRoute=true` 时回给客户端的 topic |

### `yew.mqtt-connection`(`MqttConnectionConfig`)

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `processName` | string | `"mqtt-connection"` | 辅助进程名(IPC 目标) |
| `processGroupName` | string | `"HelperGroup"` | 进程组 |
| `clusterEnabled` | bool | `false` | 开启跨节点投递 |
| `clusterPort` | int | `0` | 专用 UDP 端口(`>0` 且开启 → 接线广播器) |

### 端口配置(`yew.port.<name>`)

- `protocolType` = `mqtt` / `mqtt_over_tcp` → `openMqttProtocol = true`。
- `protocolType` = `mqtt_over_ws` / `mqtt_over_wss` → `openWebsocketProtocol` + `wsOpcode=BINARY`
  + `websocketSubprotocol="mqtt"`。
- `packTool` 应设为 `Yew\Plugins\Mqtt\MqttPack`。

```yaml
yew:
  port:
    mqtt:
      protocolType: mqtt
      host: 0.0.0.0
      port: 1883
      packTool: 'Yew\Plugins\Mqtt\MqttPack'
  mqtt-connection:
    clusterEnabled: false      # 改为 true 并设 clusterPort 即可跨节点投递
    clusterPort: 10600
```

---

## 4. 核心 API(Worker 中使用的 trait)

`Yew\Plugins\Mqtt\Topic\GetMqttTopic`:

- `hasTopic(string $topic, string $clientId): bool`
- `getSubscribers(string $topic): array`
- `addSubscription(string $topic, string $clientId, int $qos = 0): bool`
- `removeSubscription(string $topic, string $clientId): bool`
- `clearClientSubscription(string $clientId): bool`
- `publish(string $topic, $data, ?array $excludeClientIdList = null): bool`

`Yew\Plugins\Mqtt\Connection\GetMqttConnection`:

- `setFdSession` / `getFdSession`、`setFdSessionMulti` / `getFdSessionMulti`、`clearFdSession(int $fd)`
- `setClientSession` / `getClientSession`、`setClientSessionMulti` / `getClientSessionMulti`、
  `clearClientSession(string $clientId)`
- `touchActivity(int $fd)`、`setKeepAlive(int $fd, int $keepAlive)`
- `registerWill(string $clientId, array $will)` / `getWill(string $clientId): ?array` /
  `cancelWill(string $clientId)`

订阅匹配由 Trie 完成,支持 `+`(单层)、`#`(多层)与 `$` 系统主题。本地投递解析
`clientId → fd → autoBoostSend`。

---

## 5. 使用示例

```php
use Yew\Plugins\Route\Annotation\MqttController;
use Yew\Plugins\Route\Annotation\MqttMapping;
use Yew\Plugins\Mqtt\Topic\GetMqttTopic;
use Yew\Plugins\Mqtt\Connection\GetMqttConnection;
use Yew\Mqtt\Protocol\Types;

#[MqttController]
class MqttServerController extends \Yew\Plugins\Route\Controller\RouteController
{
    use GetMqttTopic, GetMqttConnection;

    #[MqttMapping("onReceive")]
    public function onReceive($data)
    {
        $type = $data['type'] ?? null;
        if ($type === Types::SUBSCRIBE) {
            foreach ($data['data']['topics'] as $topic => $opt) {
                $this->addSubscription($topic, $data['client_id'], $opt['qos'] ?? 0);
            }
        }
        if ($type === Types::PUBLISH) {
            // 投递给本节点订阅者,并(若启用集群)投递给其它节点
            $this->publish($data['data']['topic'], $data['data']['message']);
        }
    }
}
```

---

## 6. 跨节点投递

1. 设置 `yew.mqtt-connection.clusterEnabled: true` 与 `clusterPort`(`>0` 且与 `cluster.gossipPort`
   区分)。
2. 启用 [Cluster](./cluster.md) 插件(`yew.cluster.enabled = true`、`cluster-tcp` 端口)。
3. 此时 `MqttTopic::publish()` 会**本地投递一次 + 向其它节点广播**;每个接收节点的
   `mqtt-connection` 进程调用 `publishLocal()`(不会回环)。

> 若集群未启用,插件打印警告并降级为单节点投递。

---

## 7. 注意事项

- MQTT 状态机(CONNACK/SUBACK/PUBACK/Will)位于**你的**控制器,而非插件。
- `WsJsonPack` 不存在;WS 动作键为 `"p"`;`JsonPack` 使用 `"action"`。
- 跨节点投递使用的 UDP 端口**独立于** gossip 端口——务必区分。
- UDP 单包有大小限制(约 64KB 减去头部);过大的 PUBLISH 可能被丢弃。

---

## 8. 相关文档

- [Route 路由](./route.md) · [Pack 打包](./pack.md) · [Cluster 集群](./cluster.md) · [快速开始](./getting-started.md)
