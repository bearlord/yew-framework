# AMQP(RabbitMQ)

Yew 提供基于 php-amqp 的 AMQP 客户端,带连接池、发布确认(publish-confirm)与声明式消费者,用于
RabbitMQ 的生产者/消费者。

返回 [首页](../README.md) · 参见 [Queue 队列](./queue.md) · [Redis](./redis.md)。

---

## 1. 概述

- `AmqpPlugin` 读取 `yew.amqp`,构建连接池并以 `pool.<name>` 注册。
- `AmqpConsumerPlugin` + `ConsumerConfig` 声明一个消费者(队列/交换机/路由键、QoS、prefetch、
  autoDeclare …)。
- `GetAmqp` trait:`amqp(?string $name = "default")` 返回 `AMQPClient`。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Amqp\AmqpPlugin;
use Yew\Plugins\Amqp\AmqpConsumerPlugin;

$app->addPlugin(new AmqpPlugin());
$app->addPlugin(new AmqpConsumerPlugin());
```

---

## 3. 配置

**连接**(`yew.amqp.<name>`,`Config`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `host` | string | `"localhost"` | AMQP 主机 |
| `port` | int | `5672` | 端口 |
| `user` | string | `"guest"` | 用户名 |
| `password` | string | `"guest"` | 密码 |
| `vhost` | string | `"/"` | 虚拟主机 |
| `confirmTimeout` | int | `10` | 发布确认超时(100ms 单位) |
| `maxConcurrentConsumers` | int | — | 最大并发消费者 |
| `deferConsumeMessages` | int | `1` | 每次消费消息数 |
| `minConsumers` | int | `1` | 最小消费者 |
| `heartbeat` | int | `1` | 心跳 |
| `maxMessages` | int | `1` | 处理多少条后重启 |
| `prefix` | string | `""` | key 前缀 |
| `tls` | bool | `false` | 启用 TLS |
| `tlsConfig` | array | `[]` | TLS 选项 |
| `minIdleTime` | int | `10` | 空闲回收 |
| `poolMaxNumber` | int | `20` | 连接池容量 |
| `sleep` | int | `1` | 空闲休眠 |

**消费者**(`yew.amqp.<name>` 的 `ConsumerConfig` 键组):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `consumerName` | string | `""` | 消费者 id |
| `consumeQueue` | string | `""` | 消费的队列 |
| `consumeExchange` | string | `""` | 交换机 |
| `consumeExchangeType` | string | `"direct"` | 交换机类型 |
| `consumeRoutingKey` | string | `""` | 路由键 |
| `consumeTag` | string | `""` | 消费者标签 |
| `consumeNoLocal` | bool | `true` | no-local |
| `consumeNoAck` | bool | `true` | 自动 ack |
| `consumeExclusive` | bool | `false` | 独占 |
| `consumeWait` | bool | `false` | 等待消息 |
| `prefetchSize` | int | `0` | 预取大小 |
| `prefetchCount` | int | `0` | 预取条数 |
| `autoDeclare` | bool | `false` | 消费时声明 |
| `persistent` | bool | `true` | 持久消息 |
| `durable` | bool | `false` | 持久队列 |
| `messageClass` | string | `AMQPMessage::class` | 消息类 |

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

## 4. 核心 API

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

`AMQPClient`:`publishMessage($queueOrExchange, $message, $routingKey = '', $confirm = false,
$mandatory = false): bool`、`subscribe($queue, $closure)`、`getMessage()`、`confirm()`、`close()`。

---

## 5. 依赖

- 需要 `amqp` PHP 扩展。`AmqpPlugin` 为 `atAfter(YewPlugin::class)`;消费者插件依赖连接插件。

---

## 6. 注意事项

- 连接池容量由 `poolMaxNumber` 控制;耗尽会抛出
  `RuntimeException("Connection pool ... exhausted")`。
- 开启 `consumeNoAck=false` + `prefetchCount>0` 可实现至少一次投递。

---

## 7. 相关文档

- [Queue 队列](./queue.md) · [Redis](./redis.md) · [快速开始](./getting-started.md)
