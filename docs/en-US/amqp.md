# AMQP (RabbitMQ)

Yew provides an AMQP client (php-amqp based) with connection pooling, publish with confirm,
and declarative consumers. Use it for RabbitMQ producers/consumers.

Return to [homepage](../README.md) · See also [Queue](./queue.md) · [Redis](./redis.md).

---

## 1. Overview

- `AmqpPlugin` reads `yew.amqp`, builds a pool, registers it as `pool.<name>`.
- `AmqpConsumerPlugin` + `ConsumerConfig` declare a consumer (queue/exchange/routing-key, QoS,
  prefetch, autoDeclare, …).
- `GetAmqp` trait: `amqp(?string $name = "default")` returns an `AMQPClient`.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Amqp\AmqpPlugin;
use Yew\Plugins\Amqp\AmqpConsumerPlugin;

$app->addPlugin(new AmqpPlugin());
$app->addPlugin(new AmqpConsumerPlugin());
```

---

## 3. Configuration

**Connection** (`yew.amqp.<name>`, `Config`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `host` | string | `"localhost"` | AMQP host |
| `port` | int | `5672` | Port |
| `user` | string | `"guest"` | Username |
| `password` | string | `"guest"` | Password |
| `vhost` | string | `"/"` | Virtual host |
| `confirmTimeout` | int | `10` | Publish-confirm timeout (100ms units) |
| `maxConcurrentConsumers` | int | — | Max concurrent consumers |
| `deferConsumeMessages` | int | `1` | Messages per tick |
| `minConsumers` | int | `1` | Min consumers |
| `heartbeat` | int | `1` | Heartbeat |
| `maxMessages` | int | `1` | Messages before restart |
| `prefix` | string | `""` | Key prefix |
| `tls` | bool | `false` | Enable TLS |
| `tlsConfig` | array | `[]` | TLS options |
| `minIdleTime` | int | `10` | Idle recycle |
| `poolMaxNumber` | int | `20` | Pool size |
| `sleep` | int | `1` | Idle sleep |

**Consumer** (`yew.amqp.<name>` `ConsumerConfig` key group):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `consumerName` | string | `""` | Consumer id |
| `consumeQueue` | string | `""` | Queue to consume |
| `consumeExchange` | string | `""` | Exchange |
| `consumeExchangeType` | string | `"direct"` | Exchange type |
| `consumeRoutingKey` | string | `""` | Routing key |
| `consumeTag` | string | `""` | Consumer tag |
| `consumeNoLocal` | bool | `true` | no-local |
| `consumeNoAck` | bool | `true` | auto-ack |
| `consumeExclusive` | bool | `false` | exclusive |
| `consumeWait` | bool | `false` | Wait for messages |
| `prefetchSize` | int | `0` | Prefetch size |
| `prefetchCount` | int | `0` | Prefetch count |
| `autoDeclare` | bool | `false` | Declare on consume |
| `persistent` | bool | `true` | Persistent messages |
| `durable` | bool | `false` | Durable queue |
| `messageClass` | string | `AMQPMessage::class` | Message class |

```yaml
yew:
  amqp:
    default:
      host: 127.0.0.1
      port: 5672
      user: guest
      password: guest
      vhost: /
      poolMaxNumber: 10
      consumerName: order-consumer
      consumeQueue: order.create
      consumeExchange: order
      consumeExchangeType: topic
      consumeRoutingKey: 'order.#'
      prefetchCount: 10
      autoDeclare: true
```

---

## 4. Core API

```php
use Yew\Plugins\Amqp\GetAmqp;

class NotifyService {
    use GetAmqp;
    public function send(array $event): bool {
        /** @var \Yew\Plugins\Amqp\AMQPClient $c */
        $c = $this->amqp();
        return $c->publishMessage('order', json_encode($event), 'order.created', true);
    }
}
```

`AMQPClient`: `publishMessage($queueOrExchange, $message, $routingKey = '', $confirm = false,
$mandatory = false): bool`, `subscribe($queue, $closure)`, `getMessage()`, `confirm()`, `close()`.

---

## 5. Dependencies

- Requires the `amqp` PHP extension. `AmqpPlugin` is `atAfter(YewPlugin::class)`; the consumer
  plugin depends on the connection plugin.

---

## 6. Notes / Caveats

- `poolMaxNumber` controls the pool; exhaustion throws
  `RuntimeException("Connection pool ... exhausted")`.
- Enable `consumeNoAck=false` + `prefetchCount>0` for at-least-once delivery.

---

## 7. Related

- [Queue](./queue.md) · [Redis](./redis.md) · [Getting Started](./getting-started.md)
