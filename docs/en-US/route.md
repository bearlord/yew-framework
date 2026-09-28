# Route

The Route plugin maps incoming HTTP / WebSocket / TCP / UDP / MQTT events to annotation-driven
controllers. It supports path/method/port-type/port-name constraints, parameter injection and a
filter chain (CORS / JSON / XML), plus WebSocket open/message/close lifecycle routing.

Return to [homepage](../README.md) · See also [Pack](./pack.md) · [MQTT](./mqtt.md) ·
[Security](./security.md).

---

## 1. Overview

- Controllers extend `Yew\Plugins\Route\Controller\RouteController` (or the WS/TCP/UDP/MQTT/RPC
  variants) and are annotated with `@Controller`, `@GetMapping`, `@PostMapping`, `@WsMapping`,
  `@MqttMapping`, etc.
- An AOP aspect (`RouteAspect`) dispatches the right method; a route key is formatted as
  `"{port}:{METHOD}"`.
- Parameter injection annotations: `@RequestParam`, `@PathVariable`, `@RequestRawJson`,
  `@RequestBody`, `@RequestRaw`, `@RequestRawXml`, `@RequestFormData`.
- Filters: CORS, JSON response, XML response.

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Route\RoutePlugin;

$app->addPlugin(new RoutePlugin());
// auto-depends: AnnotationsScanPlugin, ValidatePlugin, PackPlugin (atAfter)
```

---

## 3. Configuration

Keys under **`yew.route`** (`RouteConfig`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `errorControllerName` | string | `NormalErrorController::class` | Fallback controller |
| `routeRoles` | array | `[]` | `RouteRoleConfig[]` (name → role) |

Per-port (`yew.port.<name>`, `RoutePortConfig`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `packTool` | string | `LenJsonPack` (non-http) / `NonJsonPack` (WS) | Pack tool |
| `routeTool` | string | `AnnotationRoute::class` | Route resolver |
| `autoSendReturnValue` | bool | `false` | Auto-write controller return for TCP/UDP |

A route role (`yew.route.role`): `name`, `route`, `controller`, `method`, `type`
(HTTP method or virtual like `mqtt`/`WS`/`TCP`), `portTypes` ([]), `portNames` ([]).

---

## 4. Core API

### Controller

```php
use Yew\Plugins\Route\Controller\RouteController;
use Yew\Plugins\Route\Annotation\Controller;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\GetHttp;

#[Controller("/api/user")]
class UserController extends RouteController
{
    use GetHttp;

    #[GetMapping("info")]
    public function info(#[Yew\Plugins\Route\Annotation\RequestParam("id")] $id)
    {
        return ['id' => $id, 'name' => 'demo'];   // auto JSON-encoded
    }
}
```

### WebSocket

```php
#[Controller("/ws")]
class ChatWsController extends RouteController
{
    #[Yew\Plugins\Route\Annotation\WsMapping("send")]
    public function send($data) { /* ... */ }
}
```

Lifecycle special routes: `/onWsOpen`, `/beforeWsClose`, `/onWsClose`, `/onConnect`,
`/beforeClose`, `/onClose`.

### `GetHttp` trait

`getRequest(): Request`, `getResponse(): Response`, `query($key=null,$default=null)`,
`post($key=null,$default=null)`, `input($key)`, `postRawJson()`, `postRawXml()`.

---

## 5. Usage Example

```yaml
yew:
  port:
    http:
      protocolType: http
      host: 0.0.0.0
      port: 8080
    ws:
      protocolType: ws
      host: 0.0.0.0
      port: 8182
      packTool: 'Yew\Plugins\Pack\PackTool\NonJsonPack'
```

```php
#[Controller("/api")]
class ApiController extends RouteController
{
    use GetHttp;
    #[GetMapping("ping")]
    public function ping() { return ['ok' => true]; }
}
```

---

## 6. Dependencies & Plugin Order

`RoutePlugin` is `atAfter(AnnotationsScanPlugin, ValidatePlugin, PackPlugin)`. Its aspect is
`atAfter(PackAspect)`. Controllers must extend `RouteController`.

---

## 7. Notes / Caveats

- The route key format is `"{port}:{METHOD}"` (`mqtt`/`WS`/`TCP`/`GET`/`POST` …).
- For TCP/UDP, set `autoSendReturnValue: true` to send the return value back automatically.

---

## 8. Related

- [Pack](./pack.md) · [MQTT](./mqtt.md) · [Security](./security.md) · [Getting Started](./getting-started.md)
