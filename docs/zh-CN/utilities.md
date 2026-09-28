# 工具组件

若干个较小组件的合集:分布式唯一 ID、通用主题树、控制台命令、热重载、错误页、健康/指标、
注解扫描、自动启动,以及底层辅助(Snowflake、Coordinator、Parallel、TokenBucket、Utils)。

返回 [首页](../README.md) · 参见 [Scheduled 定时任务](./scheduled.md) · [Cluster 集群](./cluster.md)。

---

## 1. Uid(Snowflake 唯一 ID)

```php
use Yew\Plugins\Uid\UidPlugin;
use Yew\Plugins\Uid\GetUid;

$app->addPlugin(new UidPlugin());

class X { use GetUid; public function id() { return $this->getId(); } }
```

`yew.uid`(`UidConfig`):`workerId`、`datacenterId`、`epoch`、`workerBits`(5)、`datacenterBits`(5)、
`sequenceBits`(12)、`name`、`prefix`、`minIdleTime`、`poolMaxNumber`。`getId()` 返回分布式唯一的
64 位 Snowflake ID。

---

## 2. Topic(通用主题树)

```php
use Yew\Plugins\Topic\TopicPlugin;
use Yew\Plugins\Topic\GetTopic;

$app->addPlugin(new TopicPlugin());   // 存储:memory / redis / db

class X { use GetTopic;
    public function f() {
        $this->publish('a/b', 'hi');            // 发布到主题树
        $this->subscribe('a/+', $actorName);    // 订阅一个 actor
        $this->unsubscribe('a/+', $actorName);
    }
}
```

`yew.topic`(`TopicConfig`):`storage` → `memory`/`redis`/`db` 子配置。支持 `+`/`#` 通配符。

---

## 3. Connection

`ConnectionPlugin`(`yew.connection`)集中管理每进程的客户端/会话连接(被 MQTT/WebSocket 复用)。
由 `MqttPlugin` 自动加入,一般不需要手动注册。

---

## 4. Console

`ConsolePlugin`(`yew.console`)提供 REPL/命令行环境(`Yew\Console\Console`、`Command`/`CommandConfig`),
便于在运行中的服务内进行调试与一次性命令。

---

## 5. AutoReload

`AutoReloadPlugin`(`yew.autoReload`)监听源码文件,变更时热重载 Swoole worker 进程——开发期很方便。
需要 `inotify`(或有轮询兜底)。

---

## 6. Whoops

`WhoopsPlugin` 在非生产环境为未捕获异常渲染美观的错误页(Whoops),覆盖默认错误展示。由
`yew.whoops` 开关控制。

---

## 7. Actuator

`ActuatorPlugin`(`yew.actuator`)暴露运维端点(如 `/health`、info/metrics),供编排系统探测存活/就绪。
通过路由挂载到某个 HTTP 端口即可。

---

## 8. AnnotationsScan

`AnnotationsScanPlugin`(`yew.annotations-scan`)扫描配置路径下的注解并缓存;它是 `RoutePlugin` /
`AopPlugin` 的依赖。配置 `scanPaths`。

---

## 9. Autostart

`AutostartPlugin`(`yew.autostart`)在启动时自动运行配置的进程/Actor(例如自动拉起 actor 或辅助进程)。
在配置中定义 `autostart` 条目。

---

## 10. 底层辅助

- `Yew\Snowflake\Snowflake` —— 手动生成 Snowflake ID(可配置位数/epoch)。
- `Yew\Coordinator` —— `Coordinator::waitFor($key, $cb)` / `Coordinator::notify($key)` 实现事件循环
  间的同步(等待信号后执行回调)。
- `Yew\Parallel\Parallel` / `Parallel\WaitGroup` —— 并发任务收集(见 [快速开始](./getting-started.md) §10)。
- `Yew\TokenBucket\TokenBucket` —— 独立的令牌桶(限流特性即对它的封装)。
- `Yew\Utils` —— 杂项辅助(`Yew\Utils\Str`、`Yew\Utils\Network`、`Yew\Utils\ApplicationContext` 等)。

---

## 11. 依赖

这些插件多数为 `atAfter(YewPlugin::class)`,且部分(`Topic`、`AnnotationsScan`、`Connection`)
会被其它插件(MQTT、Route、AOP)自动拉入。仅在需要独立使用时才显式启用。

---

## 12. 相关文档

- [Scheduled 定时任务](./scheduled.md) · [Cluster 集群](./cluster.md) · [快速开始](./getting-started.md)
