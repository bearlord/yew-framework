<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Client\Pool;

use Yew\Client\DbClient;

/**
 * Coroutine pool of database connections, pooling Yew\Client\DbClient
 * (which wraps Yew\Framework\Db\Connection). Supports MySQL / PostgreSQL /
 * SQL Server / Oracle.
 *
 * Borrowed clients MUST be returned via release(); prefer the scoped
 * withConnection() helper so the client goes back even on exception.
 *
 * Usage:
 *   $pool = new DbPool('mysql', '127.0.0.1', 'iot', 3306, 'root', '');
 *   $rows = $pool->withConnection(function (DbClient $db) {
 *       return $db->queryAll('SELECT * FROM t_payload LIMIT 1');
 *   });
 *
 * @method DbClient borrow()
 */
class DbPool extends ConnectionPool
{
    /**
     * Drivers must stay in sync with Yew\Client\DbClient::DRIVERS.
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
        return new DbClient(
            $this->type,
            $this->host,
            $this->dbname,
            $this->port,
            $this->username,
            $this->password,
            $this->charset,
            $this->attributes
        );
    }

    protected function isAlive(object $client): bool
    {
        return $client instanceof DbClient && $client->isConnected();
    }

    protected function destroy(object $client): void
    {
        if ($client instanceof DbClient) {
            $client->close();
        }
    }
}
