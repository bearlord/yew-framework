# MQTT

Yew provides first-class MQTT 3.1.1 / 5.0 server support: packet parsing, subscription trees
with `+`/`#` wildcards, connection state in a dedicated `mqtt-connection` helper process, and
optional cross-node delivery when the [Cluster](./clusterm.md) plugin is enabled.

Return to [homepage](../README.md) · See also [Route](./route.md) · [Pack](./pack.md) ·
[Cluster](./clusterm.md).

---

## 1. Overview

- `MqttPlugin` handles packet parsing, routing, subscription indexing and local delivery.
- The MQTT protocol state machine (CONNACK / SUBACK / PUBACK / Will) is implemented by **your**
  application's MQTT controller (use `@MqttController` / `@MqttMapping` annotations).
- `MqttConnectionPlugin` runs a `mqtt-connection` helper process that holds `fd ↔ clientId`,
  Will messages and keep-alive timers, and **optionally** fans PUBLISH out to other nodes.

> The MQTT pack tool is `Yew\Plugins\Mqtt\MqttPack`. Note: there is **no** `WsJsonPack` class;
> available pack tools are `JsonPack`, `NonJsonPack`, `LenJsonPack`, `EofJsonPack`, `StreamPack`,
> `MqttPack`. The WS convention uses the `"p"` action key; `JsonPack` uses `"action"`.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Mqtt\MqttPlugin;
use Yew\Plugins\Mqtt\Connection\MqttConnectionPlugin;

$app->addPlugin(new MqttPlugin());
$app->addPlugin(new MqttConnectionPlugin());
```

`MqttPlugin` auto-adds `UidPlugin`, `TopicPlugin` and `PackPlugin`. `MqttConnectionPlugin`
depends on `PackPlugin` (`autoBoostSend`); cross-node delivery additionally needs the
[Cluster](./clusterm.md) plugin's `GossipClusterState`.

---

## 3. Configuration

### `yew.mqtt` (`MqttPluginConfig`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `allowAnonymousAccess` | bool | `true` | Allow anonymous connect |
| `serverQos` | int | `0` | QoS used when pushing to clients |
| `useRoute` | bool | `false` | Treat topic as route path (no MQTT semantics) |
| `serverTopic` | string | `""` | Topic returned to client when `useRoute=true` |

### `yew.mqtt-connection` (`MqttConnectionConfig`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `processName` | string | `"mqtt-connection"` | Helper process name (IPC target) |
| `processGroupName` | string | `"HelperGroup"` | Process group |
| `clusterEnabled` | bool | `false` | Enable cross-node delivery |
| `clusterPort` | int | `0` | Dedicated UDP port (`>0` + enabled → wires broadcaster) |

### Port config (`yew.port.<name>`)

- `protocolType` = `mqtt` / `mqtt_over_tcp` → `openMqttProtocol = true`.
- `protocolType` = `mqtt_over_ws` / `mqtt_over_wss` → `openWebsocketProtocol` + `wsOpcode=BINARY`
  + `websocketSubprotocol="mqtt"`.
- `packTool` should be `Yew\Plugins\Mqtt\MqttPack`.

```yaml
yew:
  port:
    mqtt:
      protocolType: mqtt
      host: 0.0.0.0
      port: 1883
      packTool: 'Yew\Plugins\Mqtt\MqttPack'
  mqtt-connection:
    clusterEnabled: false      # set true + clusterPort to deliver across nodes
    clusterPort: 10600
```

---

## 4. Core API (traits used in workers)

`Yew\Plugins\Mqtt\Topic\GetMqttTopic`:

- `hasTopic(string $topic, string $clientId): bool`
- `getSubscribers(string $topic): array`
- `addSubscription(string $topic, string $clientId, int $qos = 0): bool`
- `removeSubscription(string $topic, string $clientId): bool`
- `clearClientSubscription(string $clientId): bool`
- `publish(string $topic, $data, ?array $excludeClientIdList = null): bool`

`Yew\Plugins\Mqtt\Connection\GetMqttConnection`:

- `setFdSession` / `getFdSession`, `setFdSessionMulti` / `getFdSessionMulti`, `clearFdSession(int $fd)`
- `setClientSession` / `getClientSession`, `setClientSessionMulti` / `getClientSessionMulti`,
  `clearClientSession(string $clientId)`
- `touchActivity(int $fd)`, `setKeepAlive(int $fd, int $keepAlive)`
- `registerWill(string $clientId, array $will)` / `getWill(string $clientId): ?array` /
  `cancelWill(string $clientId)`

Subscription matching is done by a Trie supporting `+` (single level), `#` (multi-level) and
`$` system topics. Local delivery resolves `clientId → fd → autoBoostSend`.

---

## 5. Usage Example

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
            // delivered to local subscribers AND (if cluster enabled) other nodes
            $this->publish($data['data']['topic'], $data['data']['message']);
        }
    }
}
```

---

## 6. Cross-Node Delivery

1. Set `yew.mqtt-connection.clusterEnabled: true` and a `clusterPort` (>0, distinct from
   `cluster.gossipPort`).
2. Enable the [Cluster](./clusterm.md) plugin (`yew.cluster.enabled = true`, `cluster-tcp` port).
3. `MqttTopic::publish()` then does **local delivery once + broadcasts to other nodes**; each
   receiving node's `mqtt-connection` process calls `publishLocal()` (no loop).

> If the cluster is not enabled, the plugin logs a warning and degrades to single-node delivery.

---

## 7. Notes / Caveats

- The MQTT state machine (CONNACK/SUBACK/PUBACK/Will) lives in **your** controller, not the plugin.
- `WsJsonPack` does not exist; WS action key is `"p"`; `JsonPack` uses `"action"`.
- Cross-node delivery uses a **separate UDP port** from gossip — keep them distinct.
- UDP packet size is limited (~64KB minus headers); oversized PUBLISH may be dropped.

---

## 8. Related

- [Route](./route.md) · [Pack](./pack.md) · [Cluster](./clusterm.md) · [Getting Started](./getting-started.md)
