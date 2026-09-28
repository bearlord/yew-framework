# Database 数据库

Yew 提供基于 PDO 的协程连接池,以及 Yii 风格的数据库访问层(SQL 构造器、`Command`、`ActiveRecord`)。
连接按协程池化,并自动归还。

返回 [首页](../README.md) · 参见 [Redis](./redis.md) · [Queue 队列](./queue.md)。

---

## 1. 概述

- `DatabasePlugin` 读取 `yew.db`,在 `beforeProcessStart` 中构建连接池并注册进 DI 容器。
- 使用 `GetDatabase` trait:`db(?string $name = "default")` 返回 `Yew\Framework\Db\Connection`
  (也支持 `name.slave` / `name.master` 读写分离)。
- 底层组件是 `Yew\Framework\Db`(PDO、`Command`、`Query`、`ActiveRecord`)。

---

## 2. 安装与插件注册

```php
use Yew\Plugins\Database\DatabasePlugin;

$app->addPlugin(new DatabasePlugin());
```

---

## 3. 配置

键位于 **`yew.db.<name>`**(`Config` 继承 `Yew\Core\Pool\Config`):

| 键 | 类型 | 默认 | 说明 |
|----|------|------|------|
| `dsn` | string | `""` | PDO DSN,如 `mysql:host=127.0.0.1;dbname=test` |
| `username` | string | `""` | 数据库用户 |
| `password` | string | `""` | 密码 |
| `tablePrefix` | string | `""` | 表前缀 |
| `charset` | string | `"utf8"` | 连接字符集 |
| `enableSchemaCache` | bool | `false` | 开启表结构缓存 |
| `schemaCacheDuration` | int | `0` | 缓存秒数(0 = 永不过期) |
| `schemaCache` | string | `"cache"` | 缓存组件 id |
| `name` | string | `"default"` | 连接名(由顶层键注入) |
| `options` | array | `[]` | 连接池 + PDO 选项 |
| `masters` / `slaves` / `masterConfig` / `slaveConfig` | array | — | 读写分离 |

> **连接池容量通过 `options` 设置**(`minConnections`、`maxConnections`、`connectTimeout`、
> `waitTimeout`、`heartbeat`、`maxIdleTime`)—— Database/Redis/AMQP **没有** `poolMaxNumber`。

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

## 4. 核心 API

```php
use Yew\Plugins\Database\GetDatabase;

class UserRepo
{
    use GetDatabase;

    public function recent(): array
    {
        /** @var \Yew\Framework\Db\Connection $db */
        $db = $this->db();                       // 池 "default"
        // $db = $this->db('log.slave');         // 从库读取

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

`Yew\Framework\Db\Connection`:`createCommand(?string $sql=null, array $params=[])`、
`beginTransaction(?string $iso=null)`、`transaction(callable $cb, ?string $iso=null)`、
`getSchema()`、`getTableSchema($name, $refresh=false)`、`quoteValue/quoteTableName/quoteColumnName`、
`getLastInsertID`。

`Yew\Framework\Db\Command`:`execute(): ?int`、`query()` / `queryAll()` / `queryOne()` /
`queryScalar()` / `queryColumn()`、`insert($table,$cols)`、`batchInsert(...)`、`upsert(...)`、
`update($table,$cols,$cond='',$params=[])`、`delete(...)`、`bindValue/bindValues`、`setSql`。

---

## 5. ActiveRecord

```php
use Yew\Framework\Db\ActiveRecord;

class User extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user}}';   // 支持 tablePrefix
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
$orders = $user->orders;                         // 惰性加载
$users = User::find()->with('orders')->all();    // 预加载
```

---

## 6. 依赖

- `DatabasePlugin` 为 `atAfter(YewPlugin::class)`。需要 PDO + 对应数据库驱动。
- 连接池容量由 `options.maxConnections` 控制。耗尽会抛出
  `RuntimeException("Connection pool ... exhausted")`。

---

## 7. 注意事项

- 连接池容量在 `options` 中,**不是** `poolMaxNumber`(该键仅用于 Queue)。
- 配置读写分离后,`db("name.slave")` / `db("name.master")` 可选择节点。

---

## 8. 相关文档

- [Redis](./redis.md) · [Queue 队列](./queue.md) · [快速开始](./getting-started.md)
