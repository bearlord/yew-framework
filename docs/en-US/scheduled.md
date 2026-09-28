# Scheduled

A cron-style task scheduler. Tasks are registered at runtime with a CRON expression (or an interval
string) and executed in a dedicated `scheduled` worker process.

Return to [homepage](../README.md) · See also [Utilities](./utilities.md) · [Getting Started](./getting-started.md).

---

## 1. Overview

- `ScheduledPlugin` runs a `scheduled` process and reads tasks from `yew.scheduled`.
- Use the `GetScheduled` trait to register tasks: `schedule(callable, name)` (interval string) or
  `scheduleCron(callable, name, cron)` (CRON expression).

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Scheduled\ScheduledPlugin;

$app->addPlugin(new ScheduledPlugin());
```

---

## 3. Configuration

**Process pool** (`yew.scheduled`, `Config`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `maxConcurrentConsumers` | int | — | Max concurrent tasks |
| `deferConsumeMessages` | int | `1` | Tasks per tick |
| `minConsumers` | int | `1` | Min consumers |
| `heartbeat` | int | `1` | Heartbeat |
| `maxMessages` | int | `1` | Messages before restart |
| `prefix` | string | `""` | Key prefix |
| `minIdleTime` | int | `10` | Idle recycle |
| `poolMaxNumber` | int | `20` | Pool size |
| `sleep` | int | `1` | Idle sleep |

**Task** (`yew.scheduled.<task>`, `ScheduledTaskConfig`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `name` | string | `""` | Task id (from top-level key) |
| `time` | string | `""` | CRON expression **or** interval string, e.g. `@every 5m`, `@daily` |
| `type` | string | `"cron"` | `cron` |
| `callablePath` | string | `""` | Class path |
| `callableMethod` | string | `"handle"` | Method |
| `callableParams` | array | `[]` | Params |
| `async` | bool | `false` | Run async |
| `timeZone` | string | `""` | TZ |

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

## 4. Core API

```php
use Yew\Plugins\Scheduled\GetScheduled;
use Yew\Core\Server\Process\Process;

class SomeProcess extends Process
{
    use GetScheduled;

    public function onProcessStart(): void
    {
        // every 5 minutes
        $this->scheduleCron(function () {
            // ...do work...
        }, 'heartbeat', '*/5 * * * *');

        // interval-style (also acceptable as CRON string)
        $this->schedule(function () {
            // ...
        }, 'flush', '@every 30s');
    }
}
```

---

## 5. Dependencies

- `ScheduledPlugin` is `atAfter(YewPlugin::class)`. Tasks run inside the `scheduled` process.

---

## 6. Notes / Caveats

- Both `schedule()` and `scheduleCron()` accept a CRON-style `time`; the `type` is `cron`.
- Consumers run in a dedicated process; register tasks in `onProcessStart`.

---

## 7. Related

- [Utilities](./utilities.md) · [Getting Started](./getting-started.md)
