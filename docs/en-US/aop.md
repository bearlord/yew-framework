# AOP (Aspect-Oriented Programming)

Yew ships a source-transforming AOP layer (a port of Go! AOP). It lets you weave cross-cutting
logic — auth, rate limiting, circuit breaking, routing, logging — into method calls without
touching business code.

Return to [homepage](../README.md) · See also [Route](./route.md) · [Security](./security.md).

---

## 1. Overview

- Aspects are declared with **docblock annotations** (`@Aspect`, `@Before`, `@After`,
  `@Around`, `@AfterThrowing`, `@AfterReturning`, `@Pointcut`) — **not** PHP 8 attributes.
  Annotation classes live in `Yew\Goaop\Lang\Annotation\`.
- An aspect must extend `Yew\Plugins\Aop\OrderAspect` (abstract, `extends Order implements Aspect`).
- Aspects are **not** auto-discovered. You must register each one explicitly with
  `AopConfig::addAspect(new MyAspect())` (the framework's own plugins do this in their `init`).
- Only classes under `yew.aop.includePaths` are woven (defaults include your `src` and the
  framework `src`).

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Aop\AopPlugin;

$app->addPlugin(new AopPlugin());
// Most feature plugins (Route, Pack, Security, RateLimit, CircuitBreaker, …) add their
// own aspects and depend on AopPlugin.
```

Register your aspect in a plugin's `init()` (or wherever the container is being assembled):

```php
/** @var \Yew\Plugins\Aop\AopConfig $aopConfig */
$aopConfig->addAspect(new \App\Aspect\TimerAspect());
```

---

## 3. Configuration

Keys live under **`yew.aop`** (`AopConfig`).

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `cacheDir` | string\|null | `runtimeDir/cache/aop` | Weaving proxy cache directory |
| `includePaths` | array | `[src, framework src]` | Only these dirs are woven |
| `excludePaths` | array | `[]` | Excluded dirs |
| `fileCache` | bool | `false` | Persist cache to disk (recommended for production) |
| `aspects` | `OrderAspect[]` | `[]` | Explicit aspect list (use `addAspect`) |

---

## 4. Core API

An advice method receives a `Yew\Goaop\Aop\Intercept\MethodInvocation`:

- `proceed()` — execute the original method and return its result (Around only; Before/After omit it).
- `getArguments()` — the call arguments.
- `getMethod()` — reflection `MethodMetadata`.
- `getThis()` — the target object.
- `__invoke($instance, $args, $variadic)`.

```php
use Yew\Plugins\Aop\OrderAspect;
use Yew\Goaop\Lang\Annotation\Aspect;
use Yew\Goaop\Lang\Annotation\Around;
use Yew\Goaop\Aop\Intercept\MethodInvocation;

/**
 * @Aspect
 */
class TimerAspect extends OrderAspect
{
    public function getName(): string { return 'TimerAspect'; }

    /**
     * @Around("within(App\Service\*) && execution(public **->*(..))")
     */
    public function time(MethodInvocation $invocation)
    {
        $t = microtime(true);
        $r = $invocation->proceed();
        echo $invocation->getMethod()->getName() . ' took ' . (microtime(true) - $t) . "s\n";
        return $r;
    }
}
```

### Pointcut DSL

- `execution(public **->onHttpRequest(*))` — any class, public `onHttpRequest`.
- `within(Yew\Core\Server\Port\IServerPort+)` — the interface and its subclasses (`+`).
- `@execution(Yew\Plugins\Security\Annotation\PreAuthorize)` — methods annotated with it.
- Combine with `||`, `&&`, `!`, and parentheses.

### Ordering aspects

In the constructor use `$this->atBefore(OtherAspect::class)` / `atAfter(...)` (from `OrderOwnerTrait`)
to control the advice chain.

---

## 5. Usage Example

```php
use Yew\Plugins\Aop\OrderAspect;
use Yew\Goaop\Lang\Annotation\Aspect;
use Yew\Goaop\Lang\Annotation\Before;
use Yew\Goaop\Aop\Intercept\MethodInvocation;

/**
 * @Aspect
 */
class AuthAspect extends OrderAspect
{
    public function getName(): string { return 'AuthAspect'; }

    /**
     * @Before("within(App\Api\*) && execution(public **->*(..))")
     */
    public function check(MethodInvocation $invocation)
    {
        // pre-logic; do NOT call proceed() in a @Before advice
    }
}
```

Then register it: `$aopConfig->addAspect(new AuthAspect());`.

---

## 6. Dependencies & Plugin Order

- No hard plugin dependency, but `AopPlugin::init` weaves classes before `beforeServerStart`,
  so it should initialize early.
- Many plugins (`Route`, `Topic`, `Pack`, `Security`, `RateLimit`, `CircuitBreaker`,
  `Whoops`, `Uid`, `Actuator`) register aspects and `atAfter`/`atBefore` one another.

---

## 7. Notes / Caveats

- **You must call `addAspect()`** — there is no annotation scan for aspects.
- Annotations are docblocks, not attributes; a missing `/**` or typo silently disables the advice.
- In production set `yew.aop.fileCache: true` so proxies are cached on disk.

---

## 8. Related

- [Route](./route.md) · [Security](./security.md) · [Rate Limit & Circuit Breaker](./rate-limit-circuit-breaker.md)
- [Getting Started](./getting-started.md)
