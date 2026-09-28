# Scheduled 定时任务

一个 cron 风格的调度器。任务在运行时用 CRON 表达式(或间隔字符串)注册,并在专用的 `scheduled`
worker 进程中执行。

返回 [首页](../README.md) · 参见 [工具组件](./utilities.md) · [快速开始](./getting-started.md)。

---

## 1. 概述

- `ScheduledPlugin` 运行一个 `scheduled` 进程,并从 `yew.scheduled` 读取任务。
- 使用 `GetScheduled` trait 注册任务:`schedule(callable, name)`(间隔字符串)或
  `scheduleCron(callable, name, cron)`(CRON 表达式)。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Scheduled\ScheduledPlugin;

$app->addPlugin(new ScheduledPlugin());
```

---

## 3. 配置

**进程池**(`yew.scheduled`,`Config`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `maxConcurrentConsumers` | int | — | 最大并发任务数 |
| `deferConsumeMessages` | int | `1` | 每次消费任务数 |
| `minConsumers` | int | `1` | 最小消费者 |
| `heartbeat` | int | `1` | 心跳 |
| `maxMessages` | int | `1` | 处理多少条后重启 |
| `prefix` | string | `""` | key 前缀 |
| `minIdleTime` | int | `10` | 空闲回收 |
| `poolMaxNumber` | int | `20` | 连接池容量 |
| `sleep` | int | `1` | 空闲休眠 |

**任务**(`yew.scheduled.<task>`,`ScheduledTaskConfig`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `name` | string | `""` | 任务 id(由顶层键注入) |
| `time` | string | `""` | CRON 表达式**或**间隔字符串,如 `@every 5m`、`@daily` |
| `type` | string | `"cron"` | `cron` |
| `callablePath` | string | `""` | 类路径 |
| `callableMethod` | string | `"handle"` | 方法 |
| `callableParams` | array | `[]` | 参数 |
| `async` | bool | `false` | 异步运行 |
| `timeZone` | string | `""` | 时区 |

```yaml
yew:
  scheduled:
    poolMaxNumber: 30
    cleanup:
      time: '@every 1h'
      type: cron
      callablePath: App\Tasks\CleanupTask
      callableMethod: handle
```

---

## 4. 核心 API

```php
use Yew\Plugins\Scheduled\GetScheduled;
use Yew\Core\Server\Process\Process;

class SomeProcess extends Process
{
    use GetScheduled;

    public function onProcessStart(): void
    {
        // 每 5 分钟
        $this->scheduleCron(function () {
            // ...执行任务...
        }, 'heartbeat', '*/5 * * * *');

        // 间隔风格(也可作为 CRON 字符串)
        $this->schedule(function () {
            // ...
        }, 'flush', '@every 30s');
    }
}
```

---

## 5. 依赖

- `ScheduledPlugin` 为 `atAfter(YewPlugin::class)`。任务运行在 `scheduled` 进程中。

---

## 6. 注意事项

- `schedule()` 与 `scheduleCron()` 都接受 CRON 风格的 `time`;`type` 为 `cron`。
- 消费者运行在专用进程;在 `onProcessStart` 中注册任务。

---

## 7. 相关文档

- [工具组件](./utilities.md) · [快速开始](./getting-started.md)
