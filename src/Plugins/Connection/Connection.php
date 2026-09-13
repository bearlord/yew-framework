<?php
/**
 * Yew framework - Connection plugin
 *
 * In-memory store of connection-level routing state, backed by Swoole\Table
 * (shared memory) so every worker/helper process can read and write it
 * directly WITHOUT going through the Connection helper process via IPC.
 *
 * Stored relations:
 *  - fd      -> uid          (connection file descriptor to subscriber uid)
 *  - clientId-> uid          (mqtt client identifier to subscriber uid)
 *  - clientId-> session_start(clean_session / clean_start flag)
 *
 * The two tables are created once in the master process (before Server::start)
 * via initTables() and are shared across all forked worker/helper processes.
 *
 * NOTE: Swoole\Table keys are limited to 64 bytes, so clientId must be <= 64
 * bytes (hash it before use if longer). The per-row `data` column holds the
 * serialized KV map and is capped at $dataColumnSize bytes.
 */

namespace Yew\Plugins\Connection;

use Swoole\Table;
use Yew\Coroutine\Server\Server;

class Connection
{
    /**
     * fd -> serialized [key => value] map. Shared memory, created in master.
     * @var Table|null
     */
    private static ?Table $fdTable = null;

    /**
     * clientId -> serialized [key => value] map. Shared memory, created in master.
     * @var Table|null
     */
    private static ?Table $clientTable = null;

    /**
     * Create the shared-memory tables. MUST be called before Server::start()
     * (in the master process) so the mapping is inherited by every forked
     * worker/helper process.
     *
     * @param int $fdSize     Max number of fd rows.
     * @param int $clientSize Max number of clientId rows.
     * @param int $dataSize   Bytes per row (holds the serialized KV map).
     * @return void
     */
    public static function initTables(int $fdSize, int $clientSize, int $dataSize): void
    {
        if (self::$fdTable === null) {
            $t = new Table($fdSize);
            $t->column('data', Table::TYPE_STRING, $dataSize);
            $t->create();
            self::$fdTable = $t;
        }
        if (self::$clientTable === null) {
            $t = new Table($clientSize);
            $t->column('data', Table::TYPE_STRING, $dataSize);
            $t->create();
            self::$clientTable = $t;
        }
    }

    /**
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Read and unserialize a row's KV map. Returns [] when the row is absent.
     *
     * @param Table $table
     * @param int|string $key
     * @return array
     */
    private static function readMap(Table $table, $key): array
    {
        $row = $table->get($key);
        if ($row === false) {
            return [];
        }
        $map = @unserialize($row['data'], ['allowed_classes' => false]);
        return is_array($map) ? $map : [];
    }

    /**
     * Serialize and write a row's KV map. Logs (once per failure) when the table
     * is full so the operator can raise the configured sizes instead of silently
     * losing data.
     *
     * @param Table $table
     * @param int|string $key
     * @param array $map
     * @return void
     */
    private static function writeMap(Table $table, $key, array $map): void
    {
        if ($table->set($key, ['data' => serialize($map)]) === false) {
            $server = Server::$instance;
            if ($server !== null) {
                $server->getLog()->warning(sprintf(
                    'Connection: shared table full on key=%s (raise fd/client table sizes)',
                    is_string($key) ? $key : (string) $key
                ));
            }
        }
    }

    /**
     * Store a key/value pair for a connection fd.
     */
    public function setFdSession(int $fd, string $key, $value): void
    {
        $map = self::readMap(self::$fdTable, $fd);
        $map[$key] = $value;
        self::writeMap(self::$fdTable, $fd, $map);
    }

    /**
     * Resolve a value stored for a connection fd by key.
     */
    public function getFdSession(int $fd, string $key = 'uid')
    {
        $map = self::readMap(self::$fdTable, $fd);
        return $map[$key] ?? null;
    }

    /**
     * Store multiple key/value pairs for a connection fd.
     */
    public function setFdSessionMulti(int $fd, array $data): void
    {
        $map = self::readMap(self::$fdTable, $fd);
        foreach ($data as $k => $v) {
            $map[$k] = $v;
        }
        self::writeMap(self::$fdTable, $fd, $map);
    }

    /**
     * Resolve all values stored for a connection fd.
     */
    public function getFdSessionMulti(int $fd): ?array
    {
        $map = self::readMap(self::$fdTable, $fd);
        return $map === [] ? null : $map;
    }

    /**
     * Remove the entire session state for a connection fd.
     */
    public function clearFdSession(int $fd): void
    {
        self::$fdTable->del($fd);
    }

    /**
     * Store a key/value pair for a clientId.
     */
    public function setClientSession(string $clientId, string $key, $value): void
    {
        $map = self::readMap(self::$clientTable, $clientId);
        $map[$key] = $value;
        self::writeMap(self::$clientTable, $clientId, $map);
    }

    /**
     * Resolve a value stored for a clientId by key.
     */
    public function getClientSession(string $clientId, string $key = 'uid')
    {
        $map = self::readMap(self::$clientTable, $clientId);
        return $map[$key] ?? null;
    }

    /**
     * Store multiple key/value pairs for a clientId.
     */
    public function setClientSessionMulti(string $clientId, array $data = []): void
    {
        $map = self::readMap(self::$clientTable, $clientId);
        foreach ($data as $k => $v) {
            $map[$k] = $v;
        }
        self::writeMap(self::$clientTable, $clientId, $map);
    }

    /**
     * Resolve all values stored for a clientId.
     */
    public function getClientSessionMulti(string $clientId): ?array
    {
        $map = self::readMap(self::$clientTable, $clientId);
        return $map === [] ? null : $map;
    }

    /**
     * Remove the entire session state for a clientId.
     */
    public function clearClientSession(string $clientId): void
    {
        self::$clientTable->del($clientId);
    }
}
