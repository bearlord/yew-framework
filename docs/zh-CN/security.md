# Security 安全、会话、校验与 JWT

Security 插件提供认证/授权原语(基于 Token + RBAC)、客户端/会话辅助,并与校验、JWT 工具集成。授权
常通过 `@PreAuthorize` AOP 注解来强制执行。

返回 [首页](../README.md) · 参见 [AOP](./aop.md) · [Route 路由](./route.md)。

---

## 1. 概述

- `SecurityPlugin` 注册认证管理器(默认 `JwtAuthenticationManager`)与授权管理器
  (`RbacAuthorizationManager`)。
- 在控制器中使用 `GetSecurity` trait 读写 principal、client id、会话,以及校验权限。
- `@PreAuthorize` 注解(配合 AOP)用于保护方法/控制器。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Security\SecurityPlugin;

$app->addPlugin(new SecurityPlugin());
```

---

## 3. 配置

键位于 **`yew.security`**(`SecurityConfig`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `securityTokenHeader` | string | `"Authorization"` | Token 头 |
| `applicationName` | string | `""` | 应用名 |
| `cipherAlgorithm` | string | `"HS256"` | JWT 算法 |
| `secret` | string | `""` | JWT 密钥 |
| `expireTime` | int | `86400` | Token 有效期(秒) |
| `authentication` | string | `JwtAuthenticationManager::class` | 认证管理器 |
| `authorization` | string | `RbacAuthorizationManager::class` | 授权管理器 |
| `principal` | string | `""` | Principal 提供方 |
| `anonymous` | bool | `true` | 允许匿名 |
| `rest` | bool | `false` | REST 模式 |
| `hmacAlgorithm` | string | `"sha256"` | HMAC 算法 |
| `secret2` | string | `""` | 备用密钥 |
| `restAuth` | string | `""` | REST 认证策略 |
| `roleConfig` | string | `""` | RBAC 角色配置路径 |

> `yew.security` 的 ProcessConfig 键定义 security 辅助进程所在进程。

```yaml
yew:
  security:
    securityTokenHeader: Authorization
    cipherAlgorithm: HS256
    secret: 'change-me'
    expireTime: 86400
    authorization: 'Yew\Plugins\Security\Auth\RbacAuthorizationManager'
    roleConfig: '/path/to/rbac.php'
    anonymous: true
```

---

## 4. 核心 API(`GetSecurity` trait)

```php
use Yew\Plugins\Security\GetSecurity;

class ApiController extends \Yew\Plugins\Route\Controller\RouteController
{
    use GetSecurity;

    public function who(): array
    {
        $ip   = $this->getClientIp();          // 请求来源 IP
        $cid  = $this->getClientId();          // mqtt/ws 客户端 id
        $auth = $this->isAuthenticated();      // 是否存在 principal?
        $user = $this->getPrincipal();         // 当前 principal

        $this->setPrincipal($user);            // 设置 principal
        $this->setAuthorities(['ROLE_USER']);  // 授予角色
        $ok   = $this->hasAuthority('ROLE_ADMIN');
        $ok   = $this->hasRole('ROLE_USER');   // hasAuthority 的别名

        // mqtt / ws 会话
        $this->setMqttClientSession($cid, ['k' => 'v']);
        $s = $this->getMqttClientSession($cid);
        return ['ip' => $ip, 'auth' => $auth];
    }
}
```

### `@PreAuthorize`

```php
use Yew\Plugins\Security\Annotation\PreAuthorize;
use Yew\Plugins\Security\GetSecurity;

class AdminController extends RouteController
{
    use GetSecurity;

    #[PreAuthorize(expression: "hasRole('ROLE_ADMIN')")]
    public function deleteAll(): array
    {
        // 仅当 principal 拥有 ROLE_ADMIN 时才会进入
        return ['ok' => true];
    }
}
```

该注解由 AOP 织入(`SecurityPlugin` 注册了一个 `MethodInterceptor`);`expression` 支持
`hasRole(...)`、`hasAuthority(...)`、`isAuthenticated()`、`anonymous()` 等辅助函数。

---

## 5. 参数校验

`ValidatePlugin`(由 `RoutePlugin` 自动加入)提供请求校验。可在控制器参数/模型上使用校验注解
(如 `@NotNull`、范围、正则)——插件在输入非法时抛异常。校验经 AOP 在控制器方法之前执行。

---

## 6. JWT 工具(`Yew\Jwt`)

`Yew\Jwt\Jwt` 用配置的算法/密钥构造/解析 Token(`encode`/`decode`)。配合 `JwtAuthenticationManager`,
签发的 Token 会在 `getPrincipal()` 中完成校验。

---

## 7. 依赖与插件顺序

- `SecurityPlugin` 为 `atAfter(AnnotationsScanPlugin, AopPlugin, ValidatePlugin, TokenBucketPlugin)`。
- `ValidatePlugin` 由 `RoutePlugin` 拉入。需要 AOP 支持 `@PreAuthorize`。

---

## 8. 注意事项

- `@PreAuthorize` 需要 AOP 织入——确保 `AopPlugin` 已加载且切面已注册。
- `anonymous: true` 允许未认证请求通过;敏感方法需显式加保护。

---

## 9. 相关文档

- [AOP](./aop.md) · [Route 路由](./route.md) · [限流与熔断](./rate-limit-circuit-breaker.md)
- [快速开始](./getting-started.md)
