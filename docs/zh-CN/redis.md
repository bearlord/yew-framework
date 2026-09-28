# Redis

基于 `phpredis` 的协程 Redis 连接池,支持单机、Cluster 与 Sentinel 模式。返回的连接通过 `__call`
转发每一条 Redis 命令。

返回 [首页](../README.md) · 参见 [Database 数据库](./database.md) · [Queue 队列](./queue.md)。

---

## 1. 概述

- `RedisPlugin` 读取 `yew.redis`,构建连接池并注册进 DI。
- 使用 `GetRedis` trait:`redis(?string $name = "default")` 返回 `RedisConnection`
  (对 `\Redis` / `RedisCluster` / `RedisSentinel` 的薄封装),可转发命令。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Redis\RedisPlugin;

$app->addPlugin(new RedisPlugin());
```

---

## 3. 配置

键位于 **`yew.redis.<name>`**(`Config`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `name` | string | `"default"` | 连接名 |
| `host` | string | `"localhost"` | Redis 主机(必填) |
| `port` | int | `6379` | 端口 |
| `password` | string | `""` | 密码 |
| `auth` | mixed | `null` | 认证串(非空则 `auth()`) |
| `database` | int | `0` | 默认 DB |
| `timeout` | float | `0.0` | 连接超时 |
| `reserved` | mixed | `null` | `connect` 保留参数 |
| `retryInterval` | int | `0` | 重连间隔 |
| `readTimeout` | float | `0.0` | 读超时 |
| `cluster` | array | `[]` | 集群配置(`enable`、`name`、`seeds` …) |
| `sentinel` | array | `[]` | 哨兵配置(`enable`、`nodes`、`masterName` …) |
| `options` | array | `[]` | `setOption` 值(含池容量) |

> 连接池容量通过 `options.maxConnections` 设置(无 `poolMaxNumber`)。

```yaml
yew:
  redis:
    default:
      host: 127.0.0.1
      port: 6379
      database: 3
      options:
        minConnections: 3
        maxConnections: 50
```

---

## 4. 核心 API

```php
use Yew\Plugins\Redis\GetRedis;

class CacheService
{
    use GetRedis;

    public function setToken(string $uid, string $token): ?string
    {
        $redis = $this->redis();            // 池 "default"
        // $redis = $this->redis("session"); // 指定连接
        $redis->set("token:$uid", $token, 3600);
        return $redis->get("token:$uid");
    }

    public function pub(string $msg): void
    {
        $this->redis()->publish('news', $msg);
    }
}
```

`RedisConnection` 转发所有 `\Redis` 方法(`set`、`get`、`hSet`、`lPush`、`publish`、
`subscribe` …)。另有:`open()`、`close()`、`getDbNum()`、`select($db)`、`setOption($name, $value)`。

---

## 5. 依赖

- 需要 `redis` PHP 扩展。`RedisPlugin` 为 `atAfter(YewPlugin::class)`。

---

## 6. 注意事项

- 连接池容量在 `options.maxConnections`;耗尽会抛出
  `RuntimeException("Connection pool ... exhausted")`。
- 使用 Sentinel/Cluster,请填 `sentinel` / `cluster` 子数组。

---

## 7. 相关文档

- [Database 数据库](./database.md) · [Queue 队列](./queue.md) · [快速开始](./getting-started.md)
