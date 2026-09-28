# Yew Framework — English Documentation

Welcome to the Yew Framework documentation. Yew is a Swoole-powered, plugin-oriented PHP
framework for building long-running, concurrent, multi-process services.

- [Getting Started](./getting-started.md) — installation, bootstrap, configuration system,
  process model, plugin lifecycle, DI container, logging, events, coroutine helpers, error handling.
- [Actor](./actor.md) — define actors, `tell`/`ask`/`askFuture`, supervision, routing, persistence.
- [Multicast](./multicast.md) — topic-based pub/sub across actor processes with `+`/`#` wildcards.
- [AOP](./aop.md) — write aspects with `@Before`/`@After`/`@Around` advices and pointcut DSL.
- [Cluster](./clusterm.md) — Gossip membership, shard routing, cross-node transport, broadcast, failover.
- [MQTT](./mqtt.md) — MQTT server support, subscription trees, connection state, cross-node delivery.
- [Route](./route.md) — annotation-driven controllers for HTTP / WS / TCP / UDP / MQTT.
- [Pack](./pack.md) — pack/unpack tools and protocol auto-detection.
- [RPC & HTTP Client](./rpc-client.md) — coroutine HTTP/WS/TCP clients and JSON-RPC.
- [Database](./database.md) — PDO connection pools and SQL queries.
- [Redis](./redis.md) — Redis connection pools (standalone/cluster/sentinel).
- [Queue](./queue.md) — Redis-backed async job queue.
- [AMQP](./amqp.md) — RabbitMQ producers and consumers.
- [Scheduled](./scheduled.md) — cron-style task scheduling.
- [Security](./security.md) — auth (`@PreAuthorize`), session, validation, JWT.
- [Rate Limit & Circuit Breaker](./rate-limit-circuit-breaker.md) — token-bucket limiting and circuit breaking.
- [Utilities](./utilities.md) — Uid, Topic, Connection, Console, AutoReload, Whoops, Actuator,
  AnnotationsScan, Autostart, Snowflake, Parallel, Coordinator, TokenBucket, Utils.

Back to [homepage](../README.md).
