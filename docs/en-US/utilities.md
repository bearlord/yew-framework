# Utilities

A grab-bag of smaller components: unique IDs, generic topic trees, console commands, hot reload,
error pages, health/metrics, annotation scanning, autostart, plus low-level helpers
(Snowflake, Coordinator, Parallel, TokenBucket, Utils).

Return to [homepage](../README.md) · See also [Scheduled](./scheduled.md) · [Cluster](./clusterm.md).

---

## 1. Uid (Snowflake IDs)

```php
use Yew\Plugins\Uid\UidPlugin;
use Yew\Plugins\Uid\GetUid;

$app->addPlugin(new UidPlugin());

class X { use GetUid; public function id() { return $this->getId(); } }
```

`yew.uid` (`UidConfig`): `workerId`, `datacenterId`, `epoch`, `workerBits` (5), `datacenterBits` (5),
`sequenceBits` (12), `name`, `prefix`, `minIdleTime`, `poolMaxNumber`. `getId()` returns a
distributed-unique 64-bit Snowflake id.

---

## 2. Topic (generic topic tree)

```php
use Yew\Plugins\Topic\TopicPlugin;
use Yew\Plugins\Topic\GetTopic;

$app->addPlugin(new TopicPlugin());   // storage: memory / redis / db

class X { use GetTopic;
    public function f() {
        $this->publish('a/b', 'hi');            // publish to a topic tree
        $this->subscribe('a/+', $actorName);    // subscribe an actor
        $this->unsubscribe('a/+', $actorName);
    }
}
```

`yew.topic` (`TopicConfig`): `storage` → `memory`/`redis`/`db` sub-config. Wildcards `+`/`#` apply.

---

## 3. Connection

`ConnectionPlugin` (`yew.connection`) centralizes per-process client/session connections
(reused by MQTT/WebSocket). Auto-added by `MqttPlugin`. Generally not registered manually.

---

## 4. Console

`ConsolePlugin` (`yew.console`) provides a REPL/command environment (`Yew\Console\Console`,
`Command`/`CommandConfig`). Useful for debugging and one-off commands inside the running server.

---

## 5. AutoReload

`AutoReloadPlugin` (`yew.auto-reload`? key `yew.autoReload`) watches source files and reloads
the Swoole worker processes on change — handy in dev. Requires `inotify` (or a polling fallback).

---

## 6. Whoops

`WhoopsPlugin` renders a pretty error page (Whoops) for uncaught exceptions in non-production,
overriding the default error display. `yew.whoops` toggles it.

---

## 7. Actuator

`ActuatorPlugin` (`yew.actuator`) exposes operational endpoints (e.g. `/health`, info/metrics)
so orchestrators can probe liveness/readiness. Mount it on an HTTP port via the route.

---

## 8. AnnotationsScan

`AnnotationsScanPlugin` (`yew.annotations-scan`) scans configured paths for annotations and
caches them; it is a dependency of `RoutePlugin`/`AopPlugin`. Configure `scanPaths`.

---

## 9. Autostart

`AutostartPlugin` (`yew.autostart`) runs configured processes/actors at boot (e.g. spawn actors
or helper processes automatically). Define `autostart` entries in config.

---

## 10. Low-level helpers

- `Yew\Snowflake\Snowflake` — manual Snowflake id generation (configurable bits/epoch).
- `Yew\Coordinator` — `Coordinator::waitFor($key, $cb)` / `Coordinator::notify($key)` to
  synchronize across the event loop (await a signal then run a callback).
- `Yew\Parallel\Parallel` / `Parallel\WaitGroup` — concurrent task gathering (see
  [Getting Started](./getting-started.md) §10).
- `Yew\TokenBucket\TokenBucket` — a standalone token-bucket (the rate-limit feature wraps this).
- `Yew\Utils` — misc helpers (`Yew\Utils\Str`, `Yew\Utils\Network`, `Yew\Utils\ApplicationContext`,
  etc.).

---

## 11. Dependencies

Most of these plugins are `atAfter(YewPlugin::class)` and several (`Topic`, `AnnotationsScan`,
`Connection`) are pulled in automatically by other plugins (MQTT, Route, AOP). Enable them only
when you need them standalone.

---

## 12. Related

- [Scheduled](./scheduled.md) · [Cluster](./clusterm.md) · [Getting Started](./getting-started.md)
