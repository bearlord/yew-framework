# AOP 面向切面编程

Yew 自带一套"源码织入"的 AOP 层(Go! AOP 的移植版)。它让你把鉴权、限流、熔断、路由、日志等
横切逻辑织入方法调用,而无需改动业务代码。

返回 [首页](../README.md) · 参见 [Route 路由](./route.md) · [Security 安全](./security.md)。

---

## 1. 概述

- 切面通过**文档注释注解**声明(`@Aspect`、`@Before`、`@After`、`@Around`、`@AfterThrowing`、
  `@AfterReturning`、`@Pointcut`)——**不是** PHP 8 属性。注解类位于
  `Yew\Goaop\Lang\Annotation\`。
- 切面必须继承 `Yew\Plugins\Aop\OrderAspect`(抽象类,`extends Order implements Aspect`)。
- 切面**不会**被自动发现。你必须显式用 `AopConfig::addAspect(new MyAspect())` 注册
  (框架自带插件都在各自的 `init` 里这样做)。
- 只有 `yew.aop.includePaths` 下的类才会被织入(默认包含你的 `src` 与框架 `src`)。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Aop\AopPlugin;

$app->addPlugin(new AopPlugin());
// 多数功能插件(Route、Pack、Security、RateLimit、CircuitBreaker …)都会注册自己的切面并依赖 AopPlugin。
```

在插件 `init()`(或装配容器的地方)注册你的切面:

```php
/** @var \Yew\Plugins\Aop\AopConfig $aopConfig */
$aopConfig->addAspect(new \App\Aspect\TimerAspect());
```

---

## 3. 配置

键位于 **`yew.aop`**(`AopConfig`)。

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `cacheDir` | string\|null | `runtimeDir/cache/aop` | 织入代理缓存目录 |
| `includePaths` | array | `[src, framework src]` | 仅这些目录会被织入 |
| `excludePaths` | array | `[]` | 排除目录 |
| `fileCache` | bool | `false` | 落盘缓存(生产建议开启) |
| `aspects` | `OrderAspect[]` | `[]` | 显式切面列表(用 `addAspect`) |

---

## 4. 核心 API

通知方法接收 `Yew\Goaop\Aop\Intercept\MethodInvocation`:

- `proceed()` —— 执行原方法并返回其结果(仅 `@Around` 调用;Before/After 不调用)。
- `getArguments()` —— 调用参数。
- `getMethod()` —— 反射 `MethodMetadata`。
- `getThis()` —— 目标对象。
- `__invoke($instance, $args, $variadic)`。

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

### 切点 DSL

- `execution(public **->onHttpRequest(*))` —— 任意类,public `onHttpRequest`。
- `within(Yew\Core\Server\Port\IServerPort+)` —— 该接口及其子类(`+`)。
- `@execution(Yew\Plugins\Security\Annotation\PreAuthorize)` —— 标注了该注解的方法。
- 用 `||`、`&&`、`!` 与括号组合。

### 切面排序

在构造函数里用 `$this->atBefore(OtherAspect::class)` / `atAfter(...)`(来自 `OrderOwnerTrait`)
控制通知链顺序。

---

## 5. 使用示例

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
        // 前置逻辑;@Before 通知中不要调用 proceed()
    }
}
```

然后注册:`$aopConfig->addAspect(new AuthAspect());`。

---

## 6. 依赖与插件顺序

- 没有强制的插件依赖,但 `AopPlugin::init` 在 `beforeServerStart` 之前完成织入,因此应尽早初始化。
- 许多插件(`Route`、`Topic`、`Pack`、`Security`、`RateLimit`、`CircuitBreaker`、`Whoops`、
  `Uid`、`Actuator`)会注册切面,并彼此 `atAfter`/`atBefore`。

---

## 7. 注意事项

- **必须调用 `addAspect()`** —— 切面不会被注解扫描自动发现。
- 注解是文档注释而非属性;遗漏 `/**` 或拼写错误会让通知静默失效。
- 生产环境建议设置 `yew.aop.fileCache: true`,将代理落盘缓存。

---

## 8. 相关文档

- [Route 路由](./route.md) · [Security 安全](./security.md) · [限流与熔断](./rate-limit-circuit-breaker.md)
- [快速开始](./getting-started.md)
