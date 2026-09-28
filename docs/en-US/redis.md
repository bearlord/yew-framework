# Redis

A coroutine Redis connection pool built on `phpredis`, supporting standalone, Cluster and Sentinel
modes. The returned connection forwards every Redis command via `__call`.

Return to [homepage](../README.md) · See also [Database](./database.md) · [Queue](./queue.md).

---

## 1. Overview

- `RedisPlugin` reads `yew.redis`, builds pools, registers them into DI.
- Use the `GetRedis` trait: `redis(?string $name = "default")` returns a `RedisConnection`
  (a thin wrapper over `\Redis` / `RedisCluster` / `RedisSentinel`) that forwards commands.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Redis\RedisPlugin;

$app->addPlugin(new RedisPlugin());
```

---

## 3. Configuration

Keys under **`yew.redis.<name>`** (`Config`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `name` | string | `"default"` | Connection name |
| `host` | string | `"localhost"` | Redis host (required) |
| `port` | int | `6379` | Port |
| `password` | string | `""` | Password |
| `auth` | mixed | `null` | Auth string (sent if non-null) |
| `database` | int | `0` | Default DB |
| `timeout` | float | `0.0` | Connect timeout |
| `reserved` | mixed | `null` | `connect` reserved arg |
| `retryInterval` | int | `0` | Reconnect interval |
| `readTimeout` | float | `0.0` | Read timeout |
| `cluster` | array | `[]` | Cluster config (`enable`, `name`, `seeds`, …) |
| `sentinel` | array | `[]` | Sentinel config (`enable`, `nodes`, `masterName`, …) |
| `options` | array | `[]` | `setOption` values (incl. pool size) |

> Pool capacity via `options.maxConnections` (no `poolMaxNumber`).

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

## 4. Core API

```php
use Yew\Plugins\Redis\GetRedis;

class CacheService
{
    use GetRedis;

    public function setToken(string $uid, string $token): ?string
    {
        $redis = $this->redis();            // pool "default"
        // $redis = $this->redis("session"); // named pool
        $redis->set("token:$uid", $token, 3600);
        return $redis->get("token:$uid");
    }

    public function pub(string $msg): void
    {
        $this->redis()->publish('news', $msg);
    }
}
```

`RedisConnection` forwards all `\Redis` methods (`set`, `get`, `hSet`, `lPush`, `publish`,
`subscribe`, …). Also: `open()`, `close()`, `getDbNum()`, `select($db)`, `setOption($name, $value)`.

---

## 5. Dependencies

- Requires the `redis` PHP extension. `RedisPlugin` is `atAfter(YewPlugin::class)`.

---

## 6. Notes / Caveats

- Pool capacity is in `options.maxConnections`; exhaustion throws
  `RuntimeException("Connection pool ... exhausted")`.
- For Sentinel/Cluster, fill the `sentinel` / `cluster` sub-arrays.

---

## 7. Related

- [Database](./database.md) · [Queue](./queue.md) · [Getting Started](./getting-started.md)
