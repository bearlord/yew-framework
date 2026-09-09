<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client\Pool;

use Yew\Framework\Db\Connection;

/**
 * Coroutine pool of database connections, wrapping Yew\Framework\Db\Connection
 * (PDO based, Yii-style). Supports MySQL / PostgreSQL / SQL Server / Oracle.
 *
 * Borrowed connections MUST be returned via release(); prefer the scoped
 * withConnection() helper so the connection goes back even on exception.
 *
 * Usage:
 *   $pool = new DbPool('mysql', '127.0.0.1', 'iot', 3306, 'root', '');
 *   $rows = $pool->withConnection(function (Connection $db) {
 *       return $db->createCommand('SELECT * FROM t_payload LIMIT 1')->queryAll();
 *   });
 *
 * @method Connection borrow()
 */
class DbPool extends ConnectionPool
{
    /**
     * Drivers supported by this pool (subset of Connection::$schemaMap).
     */
    protected const DRIVERS = [
        'mysql', 'mysqli',
        'pgsql',
        'sqlsrv', 'mssql', 'dblib',
        'oci',
    ];

    /**
     * @param string      $type        One of self::DRIVERS
     * @param string      $host        Database host
     * @param string      $dbname      Database / schema name
     * @param int|null    $port        Port; defaults per driver (mysql 3306, pgsql 5432, ...)
     * @param string      $username    DB user
     * @param string      $password    DB password
     * @param string      $charset     Connection charset (MySQL only)
     * @param array       $attributes  Extra PDO attributes merged into the connection
     * @param int         $maxConnections  Hard cap on live connections
     * @param float       $connectTimeout  Per-connection connect timeout (seconds)
     * @param float       $waitTimeout     How long borrow() waits before giving up
     */
    public function __construct(
        protected string $type,
        protected string $host,
        protected string $dbname,
        protected ?int $port = null,
        protected string $username = '',
        protected string $password = '',
        protected string $charset = 'utf8mb4',
        protected array $attributes = [],
        int $maxConnections = 64,
        float $connectTimeout = 3.0,
        float $waitTimeout = 3.0
    ) {
        if (!in_array($this->type, self::DRIVERS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported DB driver: {$this->type}. Supported: " . implode(', ', self::DRIVERS)
            );
        }

        parent::__construct($maxConnections, $connectTimeout, $waitTimeout);
    }

    protected function make(): object
    {
        $conn = new Connection([
            'dsn' => $this->buildDsn(),
            'username' => $this->username,
            'password' => $this->password,
            'charset' => $this->charset,
            'attributes' => $this->attributes,
        ]);

        $conn->open();

        return $conn;
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof Connection && $client->getIsActive();
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof Connection) {
            $client->close();
        }
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
