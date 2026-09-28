# Queue

A lightweight async job queue backed by Redis (or Beanstalk). Producers push messages from any
process; consumers run in workers and process them with closures.

Return to [homepage](../README.md) · See also [Redis](./redis.md) · [AMQP](./amqp.md).

---

## 1. Overview

- `QueuePlugin` reads `yew.queue`, builds a pool, registers it as `pool.<name>` in DI.
- `GetQueue` trait: `queue(?string $name = "default")` returns a `RedisQueue` (or Beanstalk).
- Two roles: **publish** (from anywhere) and **consume** (in a worker, via `registerConsumer`).

> Unlike Database/Redis, the Queue pool size **does** use `poolMaxNumber`.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Queue\QueuePlugin;

$app->addPlugin(new QueuePlugin());
```

---

## 3. Configuration

Keys under **`yew.queue.<name>`** (`Config`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `maxConcurrentConsumers` | int | platform-dep | Max concurrent consumers |
| `deferConsumeMessages` | int | `1` | Messages per consume tick |
| `minConsumers` | int | `1` | Min consumer count |
| `heartbeat` | int | `1` | Consumer heartbeat |
| `maxMessages` | int | `1` | Max messages before restart |
| `prefix` | string | `""` | Redis key prefix |
| `minIdleTime` | int | `10` | Idle recycle |
| `poolMaxNumber` | int | `20` | **Pool size (this plugin uses it)** |
| `driver` | string | `"redis"` | `redis` \| `beanstalk` |
| `driverConfig` | array | `[]` | Driver-specific config |
| `sleep` | int | `1` | Idle sleep |

```yaml
yew:
  queue:
    default:
      driver: redis
      poolMaxNumber: 30
      prefix: 'q:'
      driverConfig:
        name: default           # a yew.redis.<name> pool to reuse
```

---

## 4. Core API

```php
use Yew\Plugins\Queue\GetQueue;

class OrderService {
    use GetQueue;
    public function place(array $order): void {
        $this->queue()->publishMessage('order.create', json_encode($order));
    }
}

// In a worker (e.g. a Server subclass beforeConfigure / a dedicated process):
class ConsumerProcess extends \Yew\Core\Server\Process\Process {
    use GetQueue;
    public function onProcessStart(): void {
        $this->queue()->registerConsumer(function ($message, $queue) {
            $order = json_decode($message, true);
            // ... handle ...
            return true;                       // ack
        }, 'order.create');
    }
}
```

`RedisQueue`: `publishMessage($queue, $message, $delay = 0): ?int`, `subscribe($queue, $closure?)`.

---

## 5. Dependencies

- `QueuePlugin` is `atAfter(YewPlugin::class)`. The Redis driver reuses a `yew.redis` pool
  (set `driverConfig.name`).

---

## 6. Notes / Caveats

- The Queue pool size is `poolMaxNumber` (not `options.maxConnections`).
- Consumers must run inside a worker process; register them in `onProcessStart`.

---

## 7. Related

- [Redis](./redis.md) · [AMQP](./amqp.md) · [Getting Started](./getting-started.md)
