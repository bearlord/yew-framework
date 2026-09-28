# Getting Started

This guide covers installation, bootstrap, the configuration system, the process/server
model, the plugin lifecycle, dependency injection, logging, events, coroutine helpers and
error handling. It is the foundation for every other component doc.

Return to the [homepage](../README.md).

---

## 1. Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | >= 8.2  |
| Swoole      | >= 5.1 (recommended >= 6.2; coroutine / async / table / process enabled) |
| Extensions  | `pdo`, `redis`, `bcmath` (AMQP), `inotify` (optional, hot reload) |
| Composer    | >= 2.0  |

---

## 2. Installation

```bash
composer require bearlord/yew-framework
```

---

## 3. Bootstrap

A Yew application is a Swoole server. You subclass `Yew\Core\Server\Server`, register plugins,
then `configure()` + `start()`.

```php
<?php
// server.php
require __DIR__ . '/vendor/autoload.php';

use Yew\Framework\Application;

$app = new Application();
$app->addPlugin(new \Yew\Plugins\Database\DatabasePlugin());
$app->addPlugin(new \Yew\Plugins\Redis\RedisPlugin());
$app->addPlugin(new \Yew\Plugins\Route\RoutePlugin());
$app->run(App\Application::class);
```

`App\Application` is your `Server` subclass. The required hooks are:

```php
namespace App;

use Yew\Core\Server\Server;
use Yew\Core\Server\Process\Process;
use Yew\Core\Context\Context;

class Application extends Server
{
    public function pluginInitialized(): void {}
    public function configureReady(): void   {}

    public function onStart(): void {}
    public function onShutdown(): void {}
    public function onManagerStart(): void {}
    public function onManagerStop(): void {}
    public function onWorkerError(Process $process, int $exitCode, int $signal): void {}
}
```

### Ports and processes

```php
// inside your Server constructor, BEFORE configure()
$this->addPort('http', (new \Yew\Core\Server\Config\PortConfig())
    ->setHost('0.0.0.0')->setPort(8080)->setOpenHttpProtocol(true));

$this->addProcess('helper', HelperProcess::class, 'HelperGroup');
```

`addPort()` / `addProcess()` must be called **before** `configure()`.

---

## 4. Configuration System

Configuration is layered YAML, merged with depth (higher depth = higher priority):

| Depth | File | Notes |
|-------|------|-------|
| 10 | `Framework/Config/resources/base.yml` | framework built-in defaults |
| 9  | `{resources}/bootstrap.yml` | |
| 8  | `{resources}/application.yml` | base |
| 7  | `{resources}/application-{profile}.yml` | active profile (`yew.profiles.active`) |
| 6–4 | remote config (optional) | |

`{resources}` is the `RES_DIR` constant, otherwise `<rootDir>/resources`.

### How a plugin reads its config

Each plugin has a `*Config` class (e.g. `LoggerConfig`, `key = "yew.logger"`) extending
`Yew\Core\Plugins\Config\BaseConfig`. In `beforeServerStart` it calls `merge()`:

1. `toConfigArray()` reflects its camelCase properties as-is — the config keys are **camelCase** (e.g. `maxFiles`).
2. Those defaults are written at depth 10, so any YAML value overrides them.
3. `buildFromConfig()` maps the merged array back to `setXxx()` setters (`toCamelCase` strips any `_` so snake_case input also maps correctly).
4. The object is registered into the DI container via `DISet(get_class($this), $this)`.

So to override a config value, place it under the plugin's key prefix using **camelCase**:

```yaml
yew:
  logger:
    level: info
    maxFiles: 10
  server:
    workerNum: 8
```

### Reading config at runtime

```php
$value = \Yew\Core\Server\Server::$instance
    ->getConfigContext()
    ->get('yew.server.workerNum');   // dot-path lookup
```

### Variable substitution

`${VAR}` is resolved in order: PHP constant → `getenv()` → another config key → default
(`${NAME:default}`).

### Server / Port / Process keys

**`yew.server`** (selected): `name`, `workerNum` (default 1), `reactorNum`, `dispatchMode` (2),
`maxRequest` (0), `maxConn`, `daemonize` (false), `maxWaitTime` (3), `logLevel` (0–5),
`heartbeatCheckInterval`, `heartbeatIdleTime`, `maxCoroutine` (100000), `debug` (true),
`timeZone` ("Asia/Shanghai"), `enableStaticHandler`, `documentRoot`, `httpCompression` …

**`yew.port.<name>`**: `host`, `port`, `protocolType` (`http`/`ws`/`wss`/`tcp`/`udp`/`mqtt`/
`mqtt_over_ws`/`mqtt_over_wss`), `packageMaxLength`, `openHttpProtocol`, `openWebsocketProtocol`,
`openLengthCheck`, `packageLengthType`, `packageBodyOffset`, `packageLengthOffset`, `wsOpcode`,
`openMqttProtocol`.

**`yew.process.<name>`**: `class`, `group`.

---

## 5. Process & Server Model

```
Master ─ Manager ─ Worker(s) ─ Helper(s)
```

- **Master**: starts Swoole, owns the event loop.
- **Manager**: forks workers, handles reload/shutdown.
- **Worker**: runs your controllers, actors, business logic (default group `WorkerGroup`).
- **Helper**: hosts shared state (`cluster-state`, `mqtt-connection`, `topic`, `multicast`,
  `queue` …) in the `HelperGroup`.

Process groups: `DefaultGroup`, `WorkerGroup`, `ServerGroup`. Custom processes are `PROCESS_TYPE_CUSTOM`.

Lifecycle order: `configure()` → sort plugins by dependency → `init()` → `beforeServerStart()`
→ create Swoole server → fork Manager/Master → fork Workers/Helpers → for each process:
`beforeProcessStart()` (must call `$this->ready()`) → `init()` → `onProcessStart()`.

---

## 6. Plugin Lifecycle

Extend `Yew\Core\Plugin\AbstractPlugin`:

```php
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Context\Context;

class MyPlugin extends AbstractPlugin
{
    public function __construct() {
        parent::__construct();
        $this->atAfter(\Yew\Core\Plugins\Config\ConfigPlugin::class);
    }
    public function getName(): string { return 'MyPlugin'; }
    public function init(Context $context): void {}
    public function beforeServerStart(Context $context): void {}
    public function beforeProcessStart(Context $context): void {
        // ... per-process setup ...
        $this->ready();   // REQUIRED, or manager times out after 5s
    }
}
```

- `atAfter(Class)` / `atBefore(Class)` declare ordering (`OrderOwnerTrait`).
- Base plugins: `ConfigPlugin` after `EventPlugin`; `LoggerPlugin` after `ConfigPlugin`.
- In `beforeProcessStart`, **you must call `$this->ready()`**; otherwise the manager waits 5s
  then fires `PlugFailEvent`.

---

## 7. Dependency Injection

Two separate containers — **do not mix them**:

1. `Yew\Core\DI\DI` — used by the Core runtime. Global helpers:
   ```php
   use function Yew\Core\DI\DIGet;
   use function Yew\Core\DI\DISet;

   DISet(\App\Service\Cache::class, new \App\Service\Cache());
   $cache = DIGet(\App\Service\Cache::class);
   ```
   - `#[Inject]` attribute → resolve dependency by type from the container.
   - `@Value("some.key")` docblock → inject a container variable named `some.key`.
     **Important:** YAML config does **not** auto-register `@Value` variables; `BaseConfig::merge()`
     only `DISet`s the config class itself. Register `@Value` keys separately (e.g. `DISet('jwt.secret', '…')`).
   - If an entry is `Yew\Core\DI\Factory`, `DIGet` calls `->create($params)` (new instance each time).

2. `Yew\Framework\Di\Container` — used by `Yew::createObject()` (Yii-style). Used by framework
   components (DB, Queue drivers, etc.).

---

## 8. Logging

Use the `GetLogger` trait in any class:

```php
use Yew\Core\Plugins\Logger\GetLogger;

class MyProcess extends \Yew\Core\Server\Process\Process
{
    use GetLogger;
    public function onProcessStart(): void {
        $this->info('booted');
        $this->error(new \Exception('oops'));   // accepts Throwable
    }
}
```

Also: `Server::$instance->getLog()` or `DIGet(Psr\Log\LoggerInterface::class)`.
Levels (Monolog): `debug`(100) … `emergency`(600).

`yew.logger` keys: `name`("log"), `level`("debug"), `dateFormat`("Y-m-d H:i:s.u"),
`allowInlineLineBreaks`(true), `ignoreEmptyContextAndExtra`(true), `color`(true), `maxFiles`(5).

---

## 9. Events

```php
$dispatcher = \Yew\Core\Server\Server::$instance->getEventDispatcher();

$dispatcher->listen('user.registered')->call(function (\Yew\Core\Plugins\Event\Event $e) {
    $data = $e->getData();
});

$dispatcher->dispatchEvent(new \Yew\Core\Plugins\Event\Event('user.registered', ['uid' => 1]));
$dispatcher->dispatchProcessEvent($event, $procA, $procB);   // to specific processes
```

`Event`: `getType()`, `getData()`, `getSourceInfo()`, `getDstInfo()`.
`EventCall` (returned by `listen`): `->call(cb)`, `->wait(timeout)`, `->send(data)`, `->destroy()`.

---

## 10. Coroutine Helpers

```php
use Yew\Coroutine\Concurrent;
use Yew\Parallel\Parallel;

// bounded concurrency pool
$concurrent = new Concurrent(10);
foreach ($tasks as $t) { $concurrent->create(fn() => $t()); }

// gather results in parallel
$results = (new Parallel(8))
    ->add(fn() => httpA())
    ->add(fn() => httpB())
    ->wait();                       // array keyed by add() order

// global helpers (Yew\Core\Common)
goWithContext(fn() => /* inherits parent context */);
$results = \parallel([fn() => doX(), fn() => doY()], 4);
```

> There is **no** `waitFor()` / `retry()` helper in this runtime. Use `Parallel` / `Concurrent`
> or raw `Swoole\Coroutine`.

---

## 11. Error Handling & Exceptions

```php
use Yew\Core\Exception\ConfigException;
use Yew\Core\Exception\ParamException;

ConfigException::AssertNull($this, 'host', $this->getHost()); // throws if value is null
throw new ParamException('invalid client id');                // no stack trace in log
throw new \Yew\Core\Exception\Exception('boom');
```

- `Yew\Core\Exception\Exception`: base; `isTrace()` / `setTrace()` / `getTime()`.
- `ParamException` sets `setTrace(false)` so it is logged at DEBUG level (no noise).
- Uncaught exceptions are routed to the logger via `set_exception_handler`.

---

## 12. What's next?

- [Actor](./actor.md) · [Multicast](./multicast.md) · [AOP](./aop.md) · [Cluster](./clusterm.md)
- [MQTT](./mqtt.md) · [Route](./route.md) · [Pack](./pack.md) · [RPC & HTTP Client](./rpc-client.md)
- [Database](./database.md) · [Redis](./redis.md) · [Queue](./queue.md) · [AMQP](./amqp.md)
- [Scheduled](./scheduled.md) · [Security](./security.md) ·
  [Rate Limit & Circuit Breaker](./rate-limit-circuit-breaker.md) · [Utilities](./utilities.md)
