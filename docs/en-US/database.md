# Database

Yew provides a PDO-based coroutine connection pool plus a Yii-style DB access layer (SQL builder,
`Command`, and `ActiveRecord`). Connections are pooled per coroutine and released automatically.

Return to [homepage](../README.md) · See also [Redis](./redis.md) · [Queue](./queue.md).

---

## 1. Overview

- `DatabasePlugin` reads `yew.db`, builds pools in `beforeProcessStart`, and registers each into
  the DI container.
- Use the `GetDatabase` trait: `db(?string $name = "default")` returns a
  `Yew\Framework\Db\Connection` (also supports `name.slave` / `name.master` for read/write split).
- The underlying component is `Yew\Framework\Db` (PDO, `Command`, `Query`, `ActiveRecord`).

---

## 2. Installation & Plugin Registration

```php
use Yew\Plugins\Database\DatabasePlugin;

$app->addPlugin(new DatabasePlugin());
```

---

## 3. Configuration

Keys live under **`yew.db.<name>`** (`Config` extends `Yew\Core\Pool\Config`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `dsn` | string | `""` | PDO DSN, e.g. `mysql:host=127.0.0.1;dbname=test` |
| `username` | string | `""` | DB user |
| `password` | string | `""` | DB password |
| `tablePrefix` | string | `""` | Table prefix |
| `charset` | string | `"utf8"` | Connection charset |
| `enableSchemaCache` | bool | `false` | Enable schema cache |
| `schemaCacheDuration` | int | `0` | Cache seconds (0 = never expire) |
| `schemaCache` | string | `"cache"` | Cache component id |
| `name` | string | `"default"` | Connection name (injected from top-level key) |
| `options` | array | `[]` | Pool + PDO options |
| `masters` / `slaves` / `masterConfig` / `slaveConfig` | array | — | Read/write split |

> **Pool capacity is set via `options`** (`minConnections`, `maxConnections`, `connectTimeout`,
> `waitTimeout`, `heartbeat`, `maxIdleTime`) — there is **no** `poolMaxNumber` for Database/Redis/AMQP.

```yaml
yew:
  db:
    default:
      dsn: 'mysql:host=127.0.0.1;dbname=test'
      username: root
      password: root
      tablePrefix: 'ocs_'
      charset: utf8mb4
      options:
        minConnections: 2
        maxConnections: 10
```

---

## 4. Core API

```php
use Yew\Plugins\Database\GetDatabase;

class UserRepo
{
    use GetDatabase;

    public function recent(): array
    {
        /** @var \Yew\Framework\Db\Connection $db */
        $db = $this->db();                       // pool "default"
        // $db = $this->db('log.slave');         // read from a slave

        return $db->createCommand(
            'SELECT * FROM user WHERE status=:s ORDER BY id DESC LIMIT :n',
            [':s' => 1, ':n' => 10]
        )->queryAll();
    }

    public function add(string $name): void
    {
        $this->db()->createCommand()->insert('user', ['name' => $name])->execute();
    }
}
```

`Yew\Framework\Db\Connection`: `createCommand(?string $sql=null, array $params=[])`,
`beginTransaction(?string $iso=null)`, `transaction(callable $cb, ?string $iso=null)`,
`getSchema()`, `getTableSchema($name, $refresh=false)`, `quoteValue/quoteTableName/quoteColumnName`,
`getLastInsertID`.

`Yew\Framework\Db\Command`: `execute(): ?int`, `query()` / `queryAll()` / `queryOne()` /
`queryScalar()` / `queryColumn()`, `insert($table,$cols)`, `batchInsert(...)`, `upsert(...)`,
`update($table,$cols,$cond='',$params=[])`, `delete(...)`, `bindValue/bindValues`, `setSql`.

---

## 5. ActiveRecord

```php
use Yew\Framework\Db\ActiveRecord;

class User extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user}}';   // supports tablePrefix
    }
}

$user = new User();
$user->name = 'tom';
$user->save();                                  // insert

$u = User::find()->where(['name' => 'tom'])->one();
$list = User::find()->where(['status' => 1])->orderBy('id DESC')->limit(10)->all();
User::updateAll(['status' => 0], 'created_at < :t', [':t' => $cut]);

class User extends ActiveRecord {
    public function getOrders() {
        return $this->hasMany(Order::class, ['user_id' => 'id']);
    }
}
$orders = $user->orders;                         // lazy
$users = User::find()->with('orders')->all();    // eager
```

---

## 6. Dependencies

- `DatabasePlugin` is `atAfter(YewPlugin::class)`. Requires PDO + the DB driver.
- Pool size is controlled by `options.maxConnections`. Exhaustion throws
  `RuntimeException("Connection pool ... exhausted")`.

---

## 7. Notes / Caveats

- Pool capacity is in `options`, **not** `poolMaxNumber` (that key only applies to Queue).
- `db("name.slave")` / `db("name.master")` selects a node when read/write split is configured.

---

## 8. Related

- [Redis](./redis.md) · [Queue](./queue.md) · [Getting Started](./getting-started.md)
