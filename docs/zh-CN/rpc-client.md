# RPC 与 HTTP 客户端

Yew 自带轻量协程客户端(`Yew\Client`)用于 HTTP / WebSocket / TCP,以及一套 JSON-RPC 抽象
(`Yew\Rpc` + `Yew\Plugins\JsonRpc`)用于服务间调用。

返回 [首页](../README.md) · 参见 [Pack 打包](./pack.md) · [Route 路由](./route.md)。

---

## 1. 概述

- `Yew\Client\HttpClient`、`WebSocketClient`、`TcpClient`、`RedisClient`、`DbClient` —— 带连接池的
  协程客户端。
- `Yew\Rpc` + `Yew\Plugins\JsonRpc` —— 基于 HTTP / TCP-EOF / TCP-Length 的 JSON-RPC,支持服务寻址与负载均衡。

---

## 2. HTTP / WebSocket / TCP 客户端

```php
use Yew\Client\Pool\HttpClientPool;

$pool = new HttpClientPool('https://api.example.com');
$body = $pool->withConnection(function (\Yew\Client\HttpClient $c) {
    $c->get('/v1/ping');
    return $c->getBody();
});
```

```php
use Yew\Client\WebSocketClient;

$ws = new WebSocketClient('127.0.0.1', 9502, '/ws');   // 构造时即升级
$ws->push(json_encode(['p' => 'hello']));
$frame = $ws->recv();
```

`HttpClient`:`get($path, $headers=[])`、`post($path, $data, $headers=[])`、`execute($path, $method)`、
`getBody()`、`getStatusCode()`、`isConnected()`、`close()`。`HttpClientPool($host, $port=null, $options=[],
$maxConnections=64, $connectTimeout=3.0, $waitTimeout=3.0)`,配合 `withConnection(callable)` 使用。

---

## 3. JSON-RPC

```php
use Yew\Plugins\JsonRpc\Client\ServiceClient;
use Yew\Plugins\JsonRpc\Protocol;

$client = new ServiceClient([
    'serviceName' => 'user-service',
    'protocol'    => Protocol::PROTOCOL_JSON_RPC_HTTP,
    'host'        => '127.0.0.1',
    'port'        => 9503,
]);
$result = $client->getUser(['id' => 1]);   // 魔法 __call,或 ->request('getUser', ['id'=>1])
```

协议常量:`PROTOCOL_JSON_RPC`(`jsonrpc`)、`PROTOCOL_JSON_RPC_TCP_LENGTH_CHECK`、
`PROTOCOL_JSON_RPC_HTTP`。配置字段:`serviceName`、`protocol`、`host`、`port`、`nodes`、
`client`、`loadBanlance`(`"random"`)、`idGenerator`(`UniqidIdGenerator`)。

服务端:把 `Service` / `ServiceController` 作为路由 RPC 控制器暴露;用 `@ResponeJsonRpc` 注解响应。

---

## 4. 依赖

- `Yew\Client` 仅需 Swoole 协程客户端,无需插件。
- JSON-RPC 客户端依赖 `Yew\Framework\Base\Component`、`Yew\LoadBalance\Node` 以及 JsonRpc 的
  packer/transporter。

---

## 5. 注意事项

- 连接池按协程借用连接,并在 defer 时归还 —— 请用 `maxConnections` 调整容量。
- 池耗尽会抛出 `RuntimeException("Connection pool ... exhausted")`。

---

## 6. 相关文档

- [Pack 打包](./pack.md) · [Route 路由](./route.md) · [快速开始](./getting-started.md)
