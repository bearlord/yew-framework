# Yew Framework

> A high-performance, Swoole-powered, plugin-oriented PHP application framework for building
> long-running, concurrent, multi-process services (MQTT brokers, WebSocket gateways, RPC
> servers, game/IM backends, etc.).

[中文文档](docs/zh-CN/index.md) | [English Docs](docs/en-US/index.md)

---

## Why Yew

- **Built on Swoole coroutines** — millions of concurrent connections, native async I/O, no blocking.
- **True multi-process model** — Master / Manager / Worker / custom helper processes, each isolated,
  coordinated through shared memory tables and a typed IPC layer.
- **Plugin-first architecture** — every capability (Actor, Cluster, MQTT, Database, Redis, Queue,
  AOP, Route, Security …) is a plugin with a well-defined lifecycle and dependency ordering.
- **Actor model** — Akka-style actors with mailboxes, supervision, typed messages, routing
  (round-robin / consistent-hash / least-loaded) and optional event-sourcing.
- **Clustering out of the box** — Gossip membership, consistent-hash shard routing, location-transparent
  cross-node Actor transport, cluster-wide broadcast, and Actor replica / failover.
- **MQTT server support** — first-class MQTT 3.1.1/5.0 packet handling, subscription trees with
  `+`/`#` wildcards, connection state in a dedicated helper process, and optional cross-node delivery.
- **Rich data layer** — PDO connection pools, Redis pools (standalone/cluster/sentinel),
  Yii-style ActiveRecord, Redis-backed queue, and AMQP (RabbitMQ) producers/consumers.
- **AOP & cross-cutting** — source-transforming AOP (auth, rate-limit, circuit-breaker, routing,
  logging) plus scheduling, validation, session, JWT, and Snowflake ID generation.

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | >= 8.2  |
| Swoole      | >= 5.1 (recommended >= 6.2; `coroutine`, `async`, `table`, `process` enabled) |
| Extensions  | `pdo`, `redis`, `bcmath` (AMQP), `inotify` (optional, for hot reload) |
| Composer    | >= 2.0  |

---

## Quick Start

### 1. Install

```bash
composer require bearlord/yew-framework
```

### 2. Bootstrap (`server.php`)

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Yew\Framework\Application;

$app = new Application();
$app->addPlugin(new \Yew\Plugins\Database\DatabasePlugin());
$app->addPlugin(new \Yew\Plugins\Redis\RedisPlugin());
$app->addPlugin(new \Yew\Plugins\Route\RoutePlugin());
$app->addPlugin(new \Yew\Plugins\Mqtt\MqttPlugin());
$app->addPlugin(new \Yew\Plugins\Mqtt\Connection\MqttConnectionPlugin());
$app->run(App\Application::class);
```

### 3. Configuration (`resources/application.yml` + profile)

```yaml
yew:
  server:
    name: yew-app
    worker_num: 4
  port:
    http:
      protocolType: http
      host: 0.0.0.0
      port: 8080
    mqtt:
      protocolType: mqtt
      host: 0.0.0.0
      port: 1883
      packTool: 'Yew\Plugins\Mqtt\MqttPack'
  logger:
    level: debug
  db:
    default:
      dsn: 'mysql:host=127.0.0.1;dbname=test'
      username: root
      password: root
  redis:
    default:
      host: 127.0.0.1
      port: 6379
```

> Config is loaded from `resources/application.yml` then overlaid by
> `resources/application-{profile}.yml` (profile selected by `yew.profiles.active`).
> See [Getting Started](docs/en-US/getting-started.md) for the full mechanism.

### 4. Run

```bash
php server.php          # start (debug mode)
php server.php start -d # daemonize
```

---

## Architecture at a Glance

```
                 ┌───────────────────────────────────────────────┐
                 │                  Swoole Server                 │
   clients ─────▶├─ Master ─ Manager ─ Worker(s) ─ Helper(s)      │
                 │                     │            │             │
                 │            IPC ─────┴────────────┘             │
                 │            (typed, process-to-process)         │
                 └───────────────────────────────────────────────┘
                          ▲                      ▲
                    Cluster gossip       Cluster broadcast (UDP)
                          ▲                      ▲
                    Other Yew nodes  ◀────────────┘
```

- **Worker processes** run your controllers, actors, and business logic.
- **Helper processes** host shared state (cluster-state, mqtt-connection, topic, multicast, queue …).
- **IPC** lets any process call typed methods on another process without blocking the event loop.
- **Cluster** (optional) links multiple nodes via Gossip + a dedicated UDP/TCP transport.

---

## Documentation

| Section | English | 中文 |
|---------|---------|------|
| Getting Started (config, process model, plugin lifecycle, DI, logging, events, coroutine) | [en-US](docs/en-US/getting-started.md) | [zh-CN](docs/zh-CN/getting-started.md) |
| Actor model | [en-US](docs/en-US/actor.md) | [zh-CN](docs/zh-CN/actor.md) |
| Multicast (pub/sub) | [en-US](docs/en-US/multicast.md) | [zh-CN](docs/zh-CN/multicast.md) |
| AOP | [en-US](docs/en-US/aop.md) | [zh-CN](docs/zh-CN/aop.md) |
| Cluster | [en-US](docs/en-US/cluster.md) | [zh-CN](docs/zh-CN/cluster.md) |
| MQTT | [en-US](docs/en-US/mqtt.md) | [zh-CN](docs/zh-CN/mqtt.md) |
| Route | [en-US](docs/en-US/route.md) | [zh-CN](docs/zh-CN/route.md) |
| Pack | [en-US](docs/en-US/pack.md) | [zh-CN](docs/zh-CN/pack.md) |
| RPC & HTTP Client | [en-US](docs/en-US/rpc-client.md) | [zh-CN](docs/zh-CN/rpc-client.md) |
| Database | [en-US](docs/en-US/database.md) | [zh-CN](docs/zh-CN/database.md) |
| Redis | [en-US](docs/en-US/redis.md) | [zh-CN](docs/zh-CN/redis.md) |
| Queue | [en-US](docs/en-US/queue.md) | [zh-CN](docs/zh-CN/queue.md) |
| AMQP | [en-US](docs/en-US/amqp.md) | [zh-CN](docs/zh-CN/amqp.md) |
| Scheduled | [en-US](docs/en-US/scheduled.md) | [zh-CN](docs/zh-CN/scheduled.md) |
| Security & Session & Validate & JWT | [en-US](docs/en-US/security.md) | [zh-CN](docs/zh-CN/security.md) |
| Rate Limit & Circuit Breaker | [en-US](docs/en-US/rate-limit-circuit-breaker.md) | [zh-CN](docs/zh-CN/rate-limit-circuit-breaker.md) |
| Utilities (Uid, Topic, Connection, Console, AutoReload, Whoops, Actuator, AnnotationsScan, Autostart, Snowflake, Parallel, …) | [en-US](docs/en-US/utilities.md) | [zh-CN](docs/zh-CN/utilities.md) |

---

## License

MIT © bearlord
