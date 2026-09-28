# Queue 队列

一个轻量的异步任务队列,底层基于 Redis(或 Beanstalk)。生产者可在任意进程投递消息;消费者在
Worker 中运行,用闭包处理消息。

返回 [首页](../README.md) · 参见 [Redis](./redis.md) · [AMQP](./amqp.md)。

---

## 1. 概述

- `QueuePlugin` 读取 `yew.queue`,构建连接池并以 `pool.<name>` 注册进 DI。
- `GetQueue` trait:`queue(?string $name = "default")` 返回 `RedisQueue`(或 Beanstalk)。
- 两种角色:**投递**(任意位置)与**消费**(在 Worker 中,通过 `registerConsumer`)。

> 与 Database/Redis 不同,Queue 的连接池容量**确实**使用 `poolMaxNumber`。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Queue\QueuePlugin;

$app->addPlugin(new QueuePlugin());
```

---

## 3. 配置

键位于 **`yew.queue.<name>`**(`Config`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `maxConcurrentConsumers` | int | 平台相关 | 最大并发消费者数 |
| `deferConsumeMessages` | int | `1` | 每次消费的消息数 |
| `minConsumers` | int | `1` | 最小消费者数 |
| `heartbeat` | int | `1` | 消费者心跳 |
| `maxMessages` | int | `1` | 处理多少条后重启 |
| `prefix` | string | `""` | Redis key 前缀 |
| `minIdleTime` | int | `10` | 空闲回收时间 |
| `poolMaxNumber` | int | `20` | **连接池容量(本插件使用它)** |
| `driver` | string | `"redis"` | `redis` \| `beanstalk` |
| `driverConfig` | array | `[]` | 驱动专用配置 |
| `sleep` | int | `1` | 空闲休眠 |

```yaml
yew:
  queue:
    default:
      driver: redis
      poolMaxNumber: 30
      prefix: 'q:'
      driverConfig:
        name: default           # 复用的 yew.redis.<name> 池
```

---

## 4. 核心 API

```php
use Yew\Plugins\Queue\GetQueue;

class OrderService {
    use GetQueue;
    public function place(array $order): void {
        $this->queue()->publishMessage('order.create', json_encode($order));
    }
}

// 在 Worker 中(例如在 Server 子类的 beforeConfigure / 专用进程):
class ConsumerProcess extends \Yew\Core\Server\Process\Process {
    use GetQueue;
    public function onProcessStart(): void {
        $this->queue()->registerConsumer(function ($message, $queue) {
            $order = json_decode($message, true);
            // ... 处理 ...
            return true;                       // ack
        }, 'order.create');
    }
}
```

`RedisQueue`:`publishMessage($queue, $message, $delay = 0): ?int`、`subscribe($queue, $closure?)`。

---

## 5. 依赖

- `QueuePlugin` 为 `atAfter(YewPlugin::class)`。Redis 驱动复用 `yew.redis` 连接池
  (设置 `driverConfig.name`)。

---

## 6. 注意事项

- Queue 的连接池容量是 `poolMaxNumber`(而非 `options.maxConnections`)。
- 消费者必须在 Worker 进程内运行;在 `onProcessStart` 中注册。

---

## 7. 相关文档

- [Redis](./redis.md) · [AMQP](./amqp.md) · [快速开始](./getting-started.md)
