# Rate Limit & Circuit Breaker

Two cross-cutting resilience primitives, both woven via AOP annotations:

- **Rate limiting** — token-bucket per key, backed by Redis, enforced with `@RateLimit`.
- **Circuit breaking** — per-method circuit breaker (closed/open/half-open), enforced with
  `@CircuitBreaker`.

Return to [homepage](../README.md) · See also [AOP](./aop.md) · [Security](./security.md).

---

## 1. Rate Limiting

### Installation

```php
use Yew\Plugins\RateLimit\RateLimitPlugin;

$app->addPlugin(new RateLimitPlugin());
```

### Configuration (`yew.rate-limit`, `RateLimitConfig`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `processes` | string | `RateLimit` | Helper process group/name |
| `name` | string | `rate-limit` | Process name |
| `count` | int | `10` | Requests allowed per period |
| `period` | int | `1` | Period (seconds) |
| `prefix` | string | `rl:` | Redis key prefix |
| `capacity` | int | `count` | Bucket capacity |
| `redis` | string | `default` | `yew.redis` pool to use |
| `sleep` | int | `1` | Idle sleep |
| `minIdleTime` | int | `10` | Idle recycle |
| `poolMaxNumber` | int | `20` | Pool size |

### Usage (`@RateLimit`)

```php
use Yew\Plugins\RateLimit\Annotation\RateLimit;
use Yew\Plugins\RateLimit\RateLimitPlugin;   // registers RateLimitAspect

class ApiController extends \Yew\Plugins\Route\Controller\RouteController
{
    #[RateLimit(name: "search", period: 1, count: 20, fallback: "searchFallback")]
    public function search($q) { /* ... */ }

    public function searchFallback($q) { return ['error' => 'rate limited']; }
}
```

The aspect (registered by `RateLimitPlugin`) checks the bucket before the method; on breach it
calls the `fallback` method. Storage is `RedisStorage` (token bucket).

---

## 2. Circuit Breaker

### Installation

```php
use Yew\Plugins\CircuitBreaker\CircuitBreakerPlugin;

$app->addPlugin(new CircuitBreakerPlugin());
```

### Configuration (`yew.circuit-breaker`, `CircuitBreakerConfig`)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `processes` | string | `CircuitBreaker` | Helper process |
| `name` | string | `circuit-breaker` | Process name |
| `type` | string | `redis` | Storage type |
| `failureRateThreshold` | int | `50` | % failures to open |
| `slowCallDurationThreshold` | int | — | Slow-call threshold (ms) |
| `slowCallRateThreshold` | int | `100` | % slow to open |
| `waitDurationInOpenState` | int | — | Open→HalfOpen wait (ms) |
| `permittedNumberOfCallsInHalfOpenState` | int | — | Half-open trial calls |
| `minimumNumberOfCalls` | int | — | Min calls before evaluate |
| `slidingWindowType` | string | `count` | `count` \| `time` |
| `slidingWindowSize` | int | — | Window size |
| `ignoreExceptions` | array | `[]` | Exceptions that don't count |
| `recordExceptions` | array | `[]` | Exceptions that do count |

### Usage (`@CircuitBreaker`)

```php
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker;

class PaymentClient extends \Yew\Framework\Base\Component
{
    #[CircuitBreaker(name: "pay-gw", fallback: "payFallback")]
    public function charge($req) { /* call risky downstream */ }

    public function payFallback($req) { return ['ok' => false, 'degraded' => true]; }
}
```

States: **Closed** (normal) → **Open** (failing) → **Half-Open** (probing) → back to Closed/Open.
While Open, calls short-circuit to the `fallback`.

---

## 3. Dependencies & Plugin Order

- `RateLimitPlugin` is `atAfter(AopPlugin, RedisPlugin)` and adds `RateLimitAspect`.
- `CircuitBreakerPlugin` is `atAfter(AopPlugin, RedisPlugin)` and adds `CircuitBreakerAspect`.
- Both require AOP weaving; both rely on a Redis pool.

---

## 4. Notes / Caveats

- Rate-limit/breaker state lives in Redis — ensure `RedisPlugin` is registered.
- `fallback` must be a method on the **same** class.

---

## 5. Related

- [AOP](./aop.md) · [Security](./security.md) · [Redis](./redis.md) · [Getting Started](./getting-started.md)
