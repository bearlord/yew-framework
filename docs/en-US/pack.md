# Pack

The Pack plugin converts raw bytes to/from the framework's `ClientData` transport object, and
auto-enables the correct Swoole protocol (`openWebsocketProtocol` / `openLengthCheck` /
`openMqttProtocol`). `autoBoostSend` encodes a response per the peer's protocol and pushes it
(WS push or TCP send).

Return to [homepage](../README.md) · See also [Route](./route.md) · [MQTT](./mqtt.md).

---

## 1. Overview

- `IPack` implementations: `JsonPack`, `NonJsonPack`, `LenJsonPack`, `EofJsonPack`, `StreamPack`,
  `MqttPack`.
- The framework picks a default pack tool by protocol if you don't set one.
- `GetBoostSend` trait exposes `autoBoostSend(int $fd, $data, ?string $topic): bool`.

> There is **no** `WsJsonPack` class. The WS convention uses the `"p"` action key; `JsonPack`
> uses `"action"`.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Pack\PackPlugin;

$app->addPlugin(new PackPlugin());   // atAfter(AopPlugin); auto-adds AopPlugin
```

---

## 3. Configuration

Pack config is part of the port config (`yew.port.<name>`, `PackConfig`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `packTool` | string\|null | auto | One of the IPack implementations above |
| `routeTool` | string | `AnnotationRoute::class` | See Route |
| `autoSendReturnValue` | bool | `false` | See Route |

Other port keys (`PortConfig`): `openWebsocketProtocol`, `openLengthCheck`, `packageLengthType`,
`packageBodyOffset`, `packageLengthOffset`, `wsOpcode`, `openMqttProtocol`.

---

## 4. Core API

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

`ClientData` carries: `fd`, `requestMethod`, `path`, `data`, `controllerName`, `methodName`,
`params`, `request`, `response`, `annotations`, `responseRaw`, `clientInfo`.

`GetBoostSend` trait:

```php
use Yew\Plugins\Pack\GetBoostSend;

class X {
    use GetBoostSend;
    public function push(int $fd, $msg): void {
        $this->autoBoostSend($fd, $msg);   // encodes per peer protocol, wsPush or send
    }
}
```

---

## 5. Usage Example

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

## 6. Dependencies & Plugin Order

`PackPlugin` is `atAfter(AopPlugin)` and auto-adds `AopPlugin`. `RouteAspect` runs after
`PackAspect` (`RouteAspect::atAfter(PackAspect)`). `MqttConnection` / `MqttClusterBroadcaster`
depend on `GetBoostSend`.

---

## 7. Notes / Caveats

- No `WsJsonPack`. Available: `JsonPack`, `NonJsonPack`, `LenJsonPack`, `EofJsonPack`,
  `StreamPack`, `MqttPack`.
- WS inbound uses the `"p"` action key; `JsonPack` uses `"action"`.

---

## 8. Related

- [Route](./route.md) · [MQTT](./mqtt.md) · [Getting Started](./getting-started.md)
