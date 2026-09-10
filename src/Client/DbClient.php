<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client;

use Yew\Framework\Db\Connection;

/**
 * A single database connection client. Thin wrapper over
 * Yew\Framework\Db\Connection (PDO based, Yii-style) so the same object can be
 * used standalone or pooled by Yew\Client\Pool\DbPool.
 *
 * Connection is opened in the constructor; all query methods run inside the
 * current coroutine context (PDO must be runtime-hooked by Swoole, like any
 * other Yew DB usage).
 *
 * Usage:
 *   $db = new DbClient('mysql', '127.0.0.1', 'iot', 3306, 'root', '');
 *   $rows = $db->queryAll('SELECT * FROM t_payload WHERE id = :id', ['id' => 1]);
 *   $db->insert('t_payload', ['topic' => 'a', 'payload' => 'b']);
 *
 * @method Connection getConnection()
 */
class DbClient
{
    /**
     * Drivers supported (subset of Connection::$schemaMap).
     */
    protected const DRIVERS = [
        'mysql', 'mysqli',
        'pgsql',
        'sqlsrv', 'mssql', 'dblib',
        'oci',
    ];

    protected Connection $connection;

    /**
     * @param string      $type        One of self::DRIVERS
     * @param string      $host        Database host
     * @param string      $dbname      Database / schema name
     * @param int|null    $port        Port; defaults per driver
     * @param string      $username    DB user
     * @param string      $password    DB password
     * @param string      $charset     Connection charset (MySQL only)
     * @param array       $attributes  Extra PDO attributes merged into the connection
     */
    public function __construct(
        protected string $type,
        protected string $host,
        protected string $dbname,
        protected ?int $port = null,
        protected string $username = '',
        protected string $password = '',
        protected string $charset = 'utf8mb4',
        protected array $attributes = []
    ) {
        if (!in_array($this->type, self::DRIVERS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported DB driver: {$this->type}. Supported: " . implode(', ', self::DRIVERS)
            );
        }

        $this->connection = new Connection([
            'dsn' => $this->buildDsn(),
            'username' => $this->username,
            'password' => $this->password,
            'charset' => $this->charset,
            'attributes' => $this->attributes,
        ]);

        $this->connection->open();
    }

    /**
     * Fetch a single row, or null on no result.
     */
    public function query(string $sql, array $params = []): ?array
    {
        return $this->connection->createCommand($sql, $params)->query();
    }

    /**
     * Fetch all rows.
     */
    public function queryAll(string $sql, array $params = []): array
    {
        return $this->connection->createCommand($sql, $params)->queryAll();
    }

    /**
     * Fetch the first column of the first row (scalar), or false.
     */
    public function queryScalar(string $sql, array $params = [])
    {
        return $this->connection->createCommand($sql, $params)->queryScalar();
    }

    /**
     * Fetch the first column of all rows.
     */
    public function queryColumn(string $sql, array $params = []): array
    {
        return $this->connection->createCommand($sql, $params)->queryColumn();
    }

    /**
     * Execute a write statement; returns affected row count.
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->connection->createCommand($sql, $params)->execute();
    }

    /**
     * Insert a row; returns affected row count.
     */
    public function insert(string $table, array $columns): int
    {
        return $this->connection->createCommand()->insert($table, $columns)->execute();
    }

    /**
     * Update rows; returns affected row count.
     */
    public function update(string $table, array $columns, mixed $condition = '', array $params = []): int
    {
        return $this->connection->createCommand()->update($table, $columns, $condition, $params)->execute();
    }

    /**
     * Delete rows; returns affected row count.
     */
    public function delete(string $table, mixed $condition = '', array $params = []): int
    {
        return $this->connection->createCommand()->delete($table, $condition, $params)->execute();
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): mixed
    {
        return $this->connection->beginTransaction();
    }

    public function isConnected(): bool
    {
        return $this->connection->getIsActive();
    }

    public function close(): void
    {
        $this->connection->close();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Build a PDO DSN for the configured driver.
     */
    protected function buildDsn(): string
    {
        $port = $this->port ?? $this->defaultPort();

        return match ($this->type) {
            'mysql', 'mysqli' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->host, $port, $this->dbname, $this->charset
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->host, $port, $this->dbname
            ),
            'sqlsrv' => sprintf(
                'sqlsrv:Server=%s,%d;Database=%s',
                $this->host, $port, $this->dbname
            ),
            'mssql', 'dblib' => sprintf(
                'mssql:host=%s;port=%d;dbname=%s',
                $this->host, $port, $this->dbname
            ),
            'oci' => sprintf(
                'oci:dbname=//%s:%d/%s',
                $this->host, $port, $this->dbname
            ),
        };
    }

    /**
     * Per-driver default port when $port is not given.
     */
    protected function defaultPort(): int
    {
        return match ($this->type) {
            'mysql', 'mysqli' => 3306,
            'pgsql' => 5432,
            'sqlsrv', 'mssql', 'dblib' => 1433,
            'oci' => 1521,
            default => 0,
        };
    }
}
