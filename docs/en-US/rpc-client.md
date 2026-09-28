# RPC & HTTP Client

Yew ships lightweight coroutine clients (`Yew\Client`) for HTTP / WebSocket / TCP, plus a
JSON-RPC abstraction (`Yew\Rpc` + `Yew\Plugins\JsonRpc`) for service-to-service calls.

Return to [homepage](../README.md) · See also [Pack](./pack.md) · [Route](./route.md).

---

## 1. Overview

- `Yew\Client\HttpClient`, `WebSocketClient`, `TcpClient`, `RedisClient`, `DbClient` — coroutine
  clients with connection pools.
- `Yew\Rpc` + `Yew\Plugins\JsonRpc` — JSON-RPC over HTTP / TCP-EOF / TCP-Length, with service
  addressing and load balancing.

---

## 2. HTTP / WebSocket / TCP Client

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

$ws = new WebSocketClient('127.0.0.1', 9502, '/ws');   // upgrades on construct
$ws->push(json_encode(['p' => 'hello']));
$frame = $ws->recv();
```

`HttpClient`: `get($path, $headers=[])`, `post($path, $data, $headers=[])`, `execute($path, $method)`,
`getBody()`, `getStatusCode()`, `isConnected()`, `close()`. `HttpClientPool($host, $port=null, $options=[],
$maxConnections=64, $connectTimeout=3.0, $waitTimeout=3.0)` with `withConnection(callable)`.

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
$result = $client->getUser(['id' => 1]);   // magic __call, or ->request('getUser', ['id'=>1])
```

Protocol constants: `PROTOCOL_JSON_RPC` (`jsonrpc`), `PROTOCOL_JSON_RPC_TCP_LENGTH_CHECK`,
`PROTOCOL_JSON_RPC_HTTP`. Config fields: `serviceName`, `protocol`, `host`, `port`, `nodes`,
`client`, `loadBanlance` (`"random"`), `idGenerator` (`UniqidIdGenerator`).

Server side: expose a `Service` / `ServiceController` as a routed RPC controller; annotate the
response with `@ResponeJsonRpc`.

---

## 4. Dependencies

- `Yew\Client` needs only Swoole coroutine clients; no plugin required.
- JSON-RPC client depends on `Yew\Framework\Base\Component`, `Yew\LoadBalance\Node`, and the
  JsonRpc packer/transporter.

---

## 5. Notes / Caveats

- Pools borrow connections per coroutine and release on defer — size them via `maxConnections`.
- Exhausted pool throws `RuntimeException("Connection pool ... exhausted")`.

---

## 6. Related

- [Pack](./pack.md) · [Route](./route.md) · [Getting Started](./getting-started.md)
