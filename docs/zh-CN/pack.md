# Pack 打包

Pack 插件在原始字节与框架的 `ClientData` 传输对象之间互相转换,并自动启用正确的 Swoole 协议
(`openWebsocketProtocol` / `openLengthCheck` / `openMqttProtocol`)。`autoBoostSend` 按对端
协议把响应编码后推送(WS push 或 TCP send)。

返回 [首页](../README.md) · 参见 [Route 路由](./route.md) · [MQTT](./mqtt.md)。

---

## 1. 概述

- `IPack` 实现:`JsonPack`、`NonJsonPack`、`LenJsonPack`、`EofJsonPack`、`StreamPack`、`MqttPack`。
- 若你不指定,框架会按协议自动选择默认打包工具。
- `GetBoostSend` trait 提供 `autoBoostSend(int $fd, $data, ?string $topic): bool`。

> **不存在** `WsJsonPack` 类。WS 约定动作键为 `"p"`;`JsonPack` 使用 `"action"`。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Pack\PackPlugin;

$app->addPlugin(new PackPlugin());   // atAfter(AopPlugin);自动加入 AopPlugin
```

---

## 3. 配置

Pack 配置是端口配置的一部分(`yew.port.<name>`,`PackConfig`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `packTool` | string\|null | 自动 | 上述 IPack 实现之一 |
| `routeTool` | string | `AnnotationRoute::class` | 见 Route |
| `autoSendReturnValue` | bool | `false` | 见 Route |

其它端口键(`PortConfig`):`openWebsocketProtocol`、`openLengthCheck`、`packageLengthType`、
`packageBodyOffset`、`packageLengthOffset`、`wsOpcode`、`openMqttProtocol`。

---

## 4. 核心 API

```php
interface Yew\Plugins\Pack\PackTool\IPack
{
    public function encode($buffer);
    public function decode($buffer);
    public function pack($data, PortConfig $portConfig, ?string $topic = null): string;
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData;
    public static function changePortConfig(PortConfig $portConfig);
}
```

`ClientData` 携带:`fd`、`requestMethod`、`path`、`data`、`controllerName`、`methodName`、
`params`、`request`、`response`、`annotations`、`responseRaw`、`clientInfo`。

`GetBoostSend` trait:

```php
use Yew\Plugins\Pack\GetBoostSend;

class X {
    use GetBoostSend;
    public function push(int $fd, $msg): void {
        $this->autoBoostSend($fd, $msg);   // 按对端协议编码,wsPush 或 send
    }
}
```

---

## 5. 使用示例

```yaml
yew:
  port:
    tcp:
      protocolType: tcp
      host: 0.0.0.0
      port: 9501
      packTool: 'Yew\Plugins\Pack\PackTool\LenJsonPack'
      openLengthCheck: true
      packageLengthType: 'N'
      packageBodyOffset: 4
      packageMaxLength: 1048576
```

```php
use Yew\Plugins\Pack\GetBoostSend;

class TcpHandler {
    use GetBoostSend;
    public function onMessage(int $fd, $msg) {
        $this->autoBoostSend($fd, ['echo' => $msg]);
    }
}
```

---

## 6. 依赖与插件顺序

`PackPlugin` 为 `atAfter(AopPlugin)` 并自动加入 `AopPlugin`。`RouteAspect` 在 `PackAspect` 之后
运行(`RouteAspect::atAfter(PackAspect)`)。`MqttConnection` / `MqttClusterBroadcaster` 依赖
`GetBoostSend`。

---

## 7. 注意事项

- 没有 `WsJsonPack`。可用:`JsonPack`、`NonJsonPack`、`LenJsonPack`、`EofJsonPack`、
  `StreamPack`、`MqttPack`。
- WS 入站使用 `"p"` 动作键;`JsonPack` 使用 `"action"`。

---

## 8. 相关文档

- [Route 路由](./route.md) · [MQTT](./mqtt.md) · [快速开始](./getting-started.md)
