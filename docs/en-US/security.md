# Security, Session & Validation & JWT

The Security plugin provides authentication/authorization primitives (token-based + RBAC),
client/session helpers, and integrates with the validation and JWT helpers. Authorization is
often enforced via the `@PreAuthorize` AOP annotation.

Return to [homepage](../README.md) · See also [AOP](./aop.md) · [Route](./route.md).

---

## 1. Overview

- `SecurityPlugin` registers an authentication manager (`JwtAuthenticationManager` by default) and
  an authorization manager (`RbacAuthorizationManager`).
- Use the `GetSecurity` trait inside controllers to read/set the principal, client id, sessions,
  and check authorities.
- The `@PreAuthorize` annotation (combined with AOP) guards methods/controllers.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Security\SecurityPlugin;

$app->addPlugin(new SecurityPlugin());
```

---

## 3. Configuration

Keys under **`yew.security`** (`SecurityConfig`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `securityTokenHeader` | string | `"Authorization"` | Token header |
| `applicationName` | string | `""` | App name |
| `cipherAlgorithm` | string | `"HS256"` | JWT cipher |
| `secret` | string | `""` | JWT secret |
| `expireTime` | int | `86400` | Token TTL (s) |
| `authentication` | string | `JwtAuthenticationManager::class` | Auth manager |
| `authorization` | string | `RbacAuthorizationManager::class` | Authz manager |
| `principal` | string | `""` | Principal provider |
| `anonymous` | bool | `true` | Allow anonymous |
| `rest` | bool | `false` | REST mode |
| `hmacAlgorithm` | string | `"sha256"` | HMAC algo |
| `secret2` | string | `""` | Secondary secret |
| `restAuth` | string | `""` | REST auth strategy |
| `roleConfig` | string | `""` | RBAC role config path |

> The `yew.security` ProcessConfig key defines which process the security helper runs in.

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

## 4. Core API (`GetSecurity` trait)

```php
use Yew\Plugins\Security\GetSecurity;

class ApiController extends \Yew\Plugins\Route\Controller\RouteController
{
    use GetSecurity;

    public function who(): array
    {
        $ip   = $this->getClientIp();          // request source IP
        $cid  = $this->getClientId();          // mqtt/ws client id
        $auth = $this->isAuthenticated();      // is there a principal?
        $user = $this->getPrincipal();         // current principal

        $this->setPrincipal($user);            // set principal
        $this->setAuthorities(['ROLE_USER']);  // grant roles
        $ok   = $this->hasAuthority('ROLE_ADMIN');
        $ok   = $this->hasRole('ROLE_USER');   // alias of hasAuthority

        // mqtt / ws session
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
        // only reached when the principal has ROLE_ADMIN
        return ['ok' => true];
    }
}
```

The annotation is woven by AOP (`SecurityPlugin` adds a `MethodInterceptor`); the `expression`
supports helpers like `hasRole(...)`, `hasAuthority(...)`, `isAuthenticated()`, `anonymous()`.

---

## 5. Validation

`ValidatePlugin` (auto-added by `RoutePlugin`) provides request validation. Use validation
annotations on controller parameters / models (e.g. `@NotNull`, ranges, regex) — the plugin
throws on invalid input. Validation runs before the controller method via AOP.

---

## 6. JWT helper (`Yew\Jwt`)

`Yew\Jwt\Jwt` builds/parses tokens (`encode`/`decode`) using the configured algorithm/secret.
Combined with `JwtAuthenticationManager`, issued tokens are validated in `getPrincipal()`.

---

## 7. Dependencies & Plugin Order

- `SecurityPlugin` is `atAfter(AnnotationsScanPlugin, AopPlugin, ValidatePlugin, TokenBucketPlugin)`.
- `ValidatePlugin` is pulled in by `RoutePlugin`. Requires AOP for `@PreAuthorize`.

---

## 8. Notes / Caveats

- `@PreAuthorize` requires AOP weaving — ensure `AopPlugin` loads and the aspect is registered.
- `anonymous: true` lets unauthenticated requests through; guard sensitive methods explicitly.

---

## 9. Related

- [AOP](./aop.md) · [Route](./route.md) · [Rate Limit & Circuit Breaker](./rate-limit-circuit-breaker.md)
- [Getting Started](./getting-started.md)
