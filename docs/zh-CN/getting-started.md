# 快速开始

本指南涵盖安装、启动引导、配置系统、进程/服务器模型、插件生命周期、依赖注入、日志、
事件、协程辅助与错误处理,是所有其它组件文档的基础。

返回 [首页](../README.md)。

---

## 1. 环境要求

| 要求 | 版本 |
|------|------|
| PHP  | >= 8.2 |
| Swoole | >= 5.1(推荐 >= 6.2;需开启 coroutine / async / table / process) |
| 扩展 | `pdo`、`redis`、`bcmath`(AMQP)、`inotify`(可选,热重载) |
| Composer | >= 2.0 |

---

## 2. 安装

```bash
composer require bearlord/yew-framework
```

---

## 3. 启动引导

Yew 应用本质是一个 Swoole 服务。你继承 `Yew\Core\Server\Server`,注册插件,然后
`configure()` + `start()`。

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

`App\Application` 是你的 `Server` 子类,需要实现的生命周期钩子:

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

### 端口与进程

```php
// 在 Server 构造函数里,configure() 之前调用
$this->addPort('http', (new \Yew\Core\Server\Config\PortConfig())
    ->setHost('0.0.0.0')->setPort(8080)->setOpenHttpProtocol(true));

$this->addProcess('helper', HelperProcess::class, 'HelperGroup');
```

`addPort()` / `addProcess()` 必须在 `configure()` **之前**调用。

---

## 4. 配置系统

配置是分层的 YAML,按深度合并(深度越大优先级越高):

| 深度 | 文件 | 说明 |
|------|------|------|
| 10 | `Framework/Config/resources/base.yml` | 框架内置默认 |
| 9  | `{resources}/bootstrap.yml` | |
| 8  | `{resources}/application.yml` | 基础 |
| 7  | `{resources}/application-{profile}.yml` | 当前 profile(`yew.profiles.active`) |
| 6–4 | 远程配置(可选) | |

`{resources}` 为 `RES_DIR` 常量,否则为 `<rootDir>/resources`。

### 插件如何读取配置

每个插件有一个 `*Config` 类(如 `LoggerConfig`,`key = "yew.logger"`),继承
`Yew\Core\Plugins\Config\BaseConfig`。在 `beforeServerStart` 中调用 `merge()`:

1. `toConfigArray()` 直接反射 camelCase 属性作为配置键(**保持驼峰**,例如 `maxFiles`)。
2. 这些默认值写在深度 10,因此任何 YAML 值都会覆盖它。
3. `buildFromConfig()` 把合并后的数组映射回 `setXxx()` setter(`toCamelCase` 会去掉 `_`,所以写成 snake_case 也能正确映射)。
4. 对象通过 `DISet(get_class($this), $this)` 注册进 DI 容器。

所以要覆盖某个配置值,只需在插件 key 前缀下用**驼峰**放置:

```yaml
yew:
  logger:
    level: info
    maxFiles: 10
  server:
    workerNum: 8
```

### 运行时读取配置

```php
$value = \Yew\Core\Server\Server::$instance
    ->getConfigContext()
    ->get('yew.server.workerNum');   // 点号路径查找
```

### 变量替换

`${VAR}` 按以下顺序解析:PHP 常量 → `getenv()` → 其它配置键 → 默认值(`${NAME:default}`)。

### server / port / process 常用键

**`yew.server`**(部分):`name`、`workerNum`(默认 1)、`reactorNum`、`dispatchMode`(2)、
`maxRequest`(0)、`maxConn`、`daemonize`(false)、`maxWaitTime`(3)、`logLevel`(0–5)、
`heartbeatCheckInterval`、`heartbeatIdleTime`、`maxCoroutine`(100000)、`debug`(true)、
`timeZone`("Asia/Shanghai")、`enableStaticHandler`、`documentRoot`、`httpCompression` ……

**`yew.port.<name>`**:`host`、`port`、`protocolType`(`http`/`ws`/`wss`/`tcp`/`udp`/`mqtt`/
`mqtt_over_ws`/`mqtt_over_wss`)、`packageMaxLength`、`openHttpProtocol`、`openWebsocketProtocol`、
`openLengthCheck`、`packageLengthType`、`packageBodyOffset`、`packageLengthOffset`、`wsOpcode`、
`openMqttProtocol`。

**`yew.process.<name>`**:`class`、`group`。

---

## 5. 进程与服务器模型

```
Master ─ Manager ─ Worker(s) ─ Helper(s)
```

- **Master**:启动 Swoole,持有事件循环。
- **Manager**:fork worker,处理 reload/shutdown。
- **Worker**:运行业务控制器、Actor 等(默认组 `WorkerGroup`)。
- **Helper**:在 `HelperGroup` 中托管共享状态(`cluster-state`、`mqtt-connection`、
  `topic`、`multicast`、`queue` …)。

进程组:`DefaultGroup`、`WorkerGroup`、`ServerGroup`;自定义进程为 `PROCESS_TYPE_CUSTOM`。

生命周期顺序:`configure()` → 按依赖排序插件 → `init()` → `beforeServerStart()` →
创建 Swoole 服务 → fork Manager/Master → fork Worker/Helper → 每个进程:`beforeProcessStart()`
(必须调用 `$this->ready()`)→ `init()` → `onProcessStart()`。

---

## 6. 插件生命周期

继承 `Yew\Core\Plugin\AbstractPlugin`:

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
        // ... 每进程初始化 ...
        $this->ready();   // 必须调用,否则管理器 5 秒超时后判定失败
    }
}
```

- `atAfter(Class)` / `atBefore(Class)` 声明顺序(`OrderOwnerTrait`)。
- 基础插件:`ConfigPlugin` 在 `EventPlugin` 之后;`LoggerPlugin` 在 `ConfigPlugin` 之后。
- 在 `beforeProcessStart` 中**必须调用 `$this->ready()`**,否则管理器等待 5 秒后触发 `PlugFailEvent`。

---

## 7. 依赖注入

存在两套**相互独立**的容器——不要混用:

1. `Yew\Core\DI\DI` —— Core 运行时使用。全局辅助函数:
   ```php
   use function Yew\Core\DI\DIGet;
   use function Yew\Core\DI\DISet;

   DISet(\App\Service\Cache::class, new \App\Service\Cache());
   $cache = DIGet(\App\Service\Cache::class);
   ```
   - `#[Inject]` 属性 → 按类型从容器解析依赖。
   - `@Value("some.key")` 文档注释 → 注入名为 `some.key` 的容器变量。
     **注意**:YAML 配置**不会**自动注册 `@Value` 变量,`BaseConfig::merge()` 只 `DISet`
     了配置类自身。请另行注册(如 `DISet('jwt.secret', '…')`)。
   - 若条目是 `Yew\Core\DI\Factory`,`DIGet` 会调用 `->create($params)`(每次新建实例)。

2. `Yew\Framework\Di\Container` —— 由 `Yew::createObject()` 使用(Yii 风格)。用于框架组件
   (DB、Queue 驱动等)。

---

## 8. 日志

任意类中使用 `GetLogger` trait:

```php
use Yew\Core\Plugins\Logger\GetLogger;

class MyProcess extends \Yew\Core\Server\Process\Process
{
    use GetLogger;
    public function onProcessStart(): void {
        $this->info('booted');
        $this->error(new \Exception('oops'));   // 接受 Throwable
    }
}
```

另外:`Server::$instance->getLog()` 或 `DIGet(Psr\Log\LoggerInterface::class)`。
级别(Monolog):`debug`(100) … `emergency`(600)。

`yew.logger` 键:`name`("log")、`level`("debug")、`dateFormat`("Y-m-d H:i:s.u")、
`allowInlineLineBreaks`(true)、`ignoreEmptyContextAndExtra`(true)、`color`(true)、`maxFiles`(5)。

---

## 9. 事件

```php
$dispatcher = \Yew\Core\Server\Server::$instance->getEventDispatcher();

$dispatcher->listen('user.registered')->call(function (\Yew\Core\Plugins\Event\Event $e) {
    $data = $e->getData();
});

$dispatcher->dispatchEvent(new \Yew\Core\Plugins\Event\Event('user.registered', ['uid' => 1]));
$dispatcher->dispatchProcessEvent($event, $procA, $procB);   // 派发到指定进程
```

`Event`:`getType()`、`getData()`、`getSourceInfo()`、`getDstInfo()`。
`EventCall`(由 `listen` 返回):`->call(cb)`、`->wait(timeout)`、`->send(data)`、`->destroy()`。

---

## 10. 协程辅助

```php
use Yew\Coroutine\Concurrent;
use Yew\Parallel\Parallel;

// 限并发的协程池
$concurrent = new Concurrent(10);
foreach ($tasks as $t) { $concurrent->create(fn() => $t()); }

// 并行收集结果
$results = (new Parallel(8))
    ->add(fn() => httpA())
    ->add(fn() => httpB())
    ->wait();                       // 按 add 顺序返回的数组

// 全局辅助(Yew\Core\Common)
goWithContext(fn() => /* 继承父上下文 */);
$results = \parallel([fn() => doX(), fn() => doY()], 4);
```

> 本运行时**没有** `waitFor()` / `retry()` 辅助函数。请用 `Parallel` / `Concurrent`
> 或原生 `Swoole\Coroutine`。

---

## 11. 错误处理与异常

```php
use Yew\Core\Exception\ConfigException;
use Yew\Core\Exception\ParamException;

ConfigException::AssertNull($this, 'host', $this->getHost()); // 值为 null 时抛异常
throw new ParamException('invalid client id');                // 日志中不打堆栈
throw new \Yew\Core\Exception\Exception('boom');
```

- `Yew\Core\Exception\Exception`:基类;`isTrace()` / `setTrace()` / `getTime()`。
- `ParamException` 设置 `setTrace(false)`,因此以 DEBUG 级别记录(无噪音)。
- 未捕获的异常通过 `set_exception_handler` 进入日志。

---

## 12. 下一步

- [Actor](./actor.md) · [Multicast 多播](./multicast.md) · [AOP](./aop.md) · [Cluster 集群](./cluster.md)
- [MQTT](./mqtt.md) · [Route 路由](./route.md) · [Pack 打包](./pack.md) · [RPC 与 HTTP 客户端](./rpc-client.md)
- [Database 数据库](./database.md) · [Redis](./redis.md) · [Queue 队列](./queue.md) · [AMQP](./amqp.md)
- [Scheduled 定时任务](./scheduled.md) · [Security 安全](./security.md) ·
  [限流与熔断](./rate-limit-circuit-breaker.md) · [工具组件](./utilities.md)
