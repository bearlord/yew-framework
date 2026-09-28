# 限流与熔断

两套横切的韧性原语,均通过 AOP 注解织入:

- **限流** —— 基于 Redis 的令牌桶,按 key 限流,用 `@RateLimit` 标记。
- **熔断** —— 方法级的熔断器(关闭/打开/半开),用 `@CircuitBreaker` 标记。

返回 [首页](../README.md) · 参见 [AOP](./aop.md) · [Security 安全](./security.md)。

---

## 1. 限流

### 安装

```php
use Yew\Plugins\RateLimit\RateLimitPlugin;

$app->addPlugin(new RateLimitPlugin());
```

### 配置(`yew.rate-limit`,`RateLimitConfig`)

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `processes` | string | `RateLimit` | 辅助进程组/名 |
| `name` | string | `rate-limit` | 进程名 |
| `count` | int | `10` | 每个周期允许的请求数 |
| `period` | int | `1` | 周期(秒) |
| `prefix` | string | `rl:` | Redis key 前缀 |
| `capacity` | int | `count` | 桶容量 |
| `redis` | string | `default` | 使用的 `yew.redis` 池 |
| `sleep` | int | `1` | 空闲休眠 |
| `minIdleTime` | int | `10` | 空闲回收 |
| `poolMaxNumber` | int | `20` | 连接池容量 |

### 用法(`@RateLimit`)

```php
use Yew\Plugins\RateLimit\Annotation\RateLimit;

class ApiController extends \Yew\Plugins\Route\Controller\RouteController
{
    #[RateLimit(name: "search", period: 1, count: 20, fallback: "searchFallback")]
    public function search($q) { /* ... */ }

    public function searchFallback($q) { return ['error' => 'rate limited']; }
}
```

由 `RateLimitPlugin` 注册的切面在方法前检查令牌桶;超限则调用 `fallback` 方法。存储为
`RedisStorage`(令牌桶)。

---

## 2. 熔断器

### 安装

```php
use Yew\Plugins\CircuitBreaker\CircuitBreakerPlugin;

$app->addPlugin(new CircuitBreakerPlugin());
```

### 配置(`yew.circuit-breaker`,`CircuitBreakerConfig`)

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `processes` | string | `CircuitBreaker` | 辅助进程 |
| `name` | string | `circuit-breaker` | 进程名 |
| `type` | string | `redis` | 存储类型 |
| `failureRateThreshold` | int | `50` | 触发打开的失败率(%) |
| `slowCallDurationThreshold` | int | — | 慢调用阈值(ms) |
| `slowCallRateThreshold` | int | `100` | 触发打开的慢调用率(%) |
| `waitDurationInOpenState` | int | — | 打开→半开等待(ms) |
| `permittedNumberOfCallsInHalfOpenState` | int | — | 半开试探调用数 |
| `minimumNumberOfCalls` | int | — | 评估前最小调用数 |
| `slidingWindowType` | string | `count` | `count` \| `time` |
| `slidingWindowSize` | int | — | 窗口大小 |
| `ignoreExceptions` | array | `[]` | 不计数的异常 |
| `recordExceptions` | array | `[]` | 计数的异常 |

### 用法(`@CircuitBreaker`)

```php
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker;

class PaymentClient extends \Yew\Framework\Base\Component
{
    #[CircuitBreaker(name: "pay-gw", fallback: "payFallback")]
    public function charge($req) { /* 调用高风险下游 */ }

    public function payFallback($req) { return ['ok' => false, 'degraded' => true]; }
}
```

状态:**Closed**(正常)→ **Open**(失败)→ **Half-Open**(探测)→ 回到 Closed/Open。处于 Open 时,
调用直接短路到 `fallback`。

---

## 3. 依赖与插件顺序

- `RateLimitPlugin` 为 `atAfter(AopPlugin, RedisPlugin)`,并注册 `RateLimitAspect`。
- `CircuitBreakerPlugin` 为 `atAfter(AopPlugin, RedisPlugin)`,并注册 `CircuitBreakerAspect`。
- 两者都需要 AOP 织入,且都依赖 Redis 连接池。

---

## 4. 注意事项

- 限流/熔断状态保存在 Redis —— 请确保 `RedisPlugin` 已注册。
- `fallback` 必须是**同一类**上的方法。

---

## 5. 相关文档

- [AOP](./aop.md) · [Security 安全](./security.md) · [Redis](./redis.md) · [快速开始](./getting-started.md)
