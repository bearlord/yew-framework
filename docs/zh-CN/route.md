# Route 路由

Route 插件把进来的 HTTP / WebSocket / TCP / UDP / MQTT 事件路由到**注解驱动的控制器**。它支持
路径/方法/端口类型/端口名约束、参数注入与过滤器链(CORS / JSON / XML),以及 WebSocket 的
open/message/close 生命周期路由。

返回 [首页](../README.md) · 参见 [Pack 打包](./pack.md) · [MQTT](./mqtt.md) ·
[Security 安全](./security.md)。

---

## 1. 概述

- 控制器继承 `Yew\Plugins\Route\Controller\RouteController`(或 WS/TCP/UDP/MQTT/RPC 变体),并用
  `@Controller`、`@GetMapping`、`@PostMapping`、`@WsMapping`、`@MqttMapping` 等注解。
- 一个 AOP 切面(`RouteAspect`)分发到正确的方法;路由键格式为 `"{port}:{METHOD}"`。
- 参数注入注解:`@RequestParam`、`@PathVariable`、`@RequestRawJson`、`@RequestBody`、
  `@RequestRaw`、`@RequestRawXml`、`@RequestFormData`。
- 过滤器:CORS、JSON 响应、XML 响应。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Route\RoutePlugin;

$app->addPlugin(new RoutePlugin());
// 自动依赖:AnnotationsScanPlugin、ValidatePlugin、PackPlugin(atAfter)
```

---

## 3. 配置

**`yew.route`**(`RouteConfig`)下的键:

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `errorControllerName` | string | `NormalErrorController::class` | 兜底控制器 |
| `routeRoles` | array | `[]` | `RouteRoleConfig[]`(名 → 角色) |

每端口(`yew.port.<name>`,`RoutePortConfig`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `packTool` | string | 非 HTTP 用 `LenJsonPack` / WS 用 `NonJsonPack` | 打包工具 |
| `routeTool` | string | `AnnotationRoute::class` | 路由解析器 |
| `autoSendReturnValue` | bool | `false` | TCP/UDP 是否自动回写返回值 |

路由角色(`yew.route.role`):`name`、`route`、`controller`、`method`(HTTP 方法或虚拟方法如
`mqtt`/`WS`/`TCP`)、`portTypes`([])、`portNames`([])。

---

## 4. 核心 API

### 控制器

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
        return ['id' => $id, 'name' => 'demo'];   // 自动 JSON 编码
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

生命周期特殊路由:`/onWsOpen`、`/beforeWsClose`、`/onWsClose`、`/onConnect`、
`/beforeClose`、`/onClose`。

### `GetHttp` trait

`getRequest(): Request`、`getResponse(): Response`、`query($key=null,$default=null)`、
`post($key=null,$default=null)`、`input($key)`、`postRawJson()`、`postRawXml()`。

---

## 5. 使用示例

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

## 6. 依赖与插件顺序

`RoutePlugin` 为 `atAfter(AnnotationsScanPlugin, ValidatePlugin, PackPlugin)`。其切面
`atAfter(PackAspect)`。控制器必须继承 `RouteController`。

---

## 7. 注意事项

- 路由键格式为 `"{port}:{METHOD}"`(`mqtt`/`WS`/`TCP`/`GET`/`POST` …)。
- TCP/UDP 端口设置 `autoSendReturnValue: true` 才会自动回写返回值。

---

## 8. 相关文档

- [Pack 打包](./pack.md) · [MQTT](./mqtt.md) · [Security 安全](./security.md) · [快速开始](./getting-started.md)
