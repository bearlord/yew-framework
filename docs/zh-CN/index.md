# Yew 框架中文文档

欢迎阅读 Yew 框架中文文档。Yew 是一个基于 Swoole、以插件为导向的 PHP 框架,用于构建
长驻内存、高并发、多进程的常驻服务(MQTT Broker、WebSocket 网关、RPC 服务、游戏/IM 后端等)。

- [快速开始](./getting-started.md) — 安装、启动引导、配置系统、进程模型、插件生命周期、DI 容器、日志、事件、协程辅助、错误处理。
- [Actor](./actor.md) — 定义 Actor,`tell`/`ask`/`askFuture`、监督策略、路由、事件溯源。
- [Multicast 多播](./multicast.md) — 跨 Actor 进程的基于主题的发布/订阅,支持 `+`/`#` 通配符。
- [AOP](./aop.md) — 用 `@Before`/`@After`/`@Around` 通知与切点 DSL 编写切面。
- [Cluster 集群](./cluster.md) — Gossip 成员管理、分片路由、跨节点传输、集群广播、故障转移。
- [MQTT](./mqtt.md) — MQTT 服务端支持、订阅树、连接状态、跨节点投递。
- [Route 路由](./route.md) — HTTP / WS / TCP / UDP / MQTT 的注解驱动控制器。
- [Pack 打包](./pack.md) — 打包/解包工具与协议自动识别。
- [RPC 与 HTTP 客户端](./rpc-client.md) — 协程 HTTP/WS/TCP 客户端与 JSON-RPC。
- [Database 数据库](./database.md) — PDO 连接池与原生 SQL。
- [Redis](./redis.md) — Redis 连接池(单机/集群/哨兵)。
- [Queue 队列](./queue.md) — 基于 Redis 的异步任务队列。
- [AMQP](./amqp.md) — RabbitMQ 生产者与消费者。
- [Scheduled 定时任务](./scheduled.md) — cron 风格的任务调度。
- [Security 安全](./security.md) — 鉴权(`@PreAuthorize`)、会话、参数校验、JWT。
- [限流与熔断](./rate-limit-circuit-breaker.md) — 令牌桶限流与熔断器。
- [工具组件](./utilities.md) — Uid、Topic、Connection、Console、AutoReload、Whoops、Actuator、
  AnnotationsScan、Autostart、Snowflake、Parallel、Coordinator、TokenBucket、Utils。

返回 [首页](../README.md)。
