<?php
/**
 * Yew framework - Connection plugin
 *
 * Worker-side helper that reads/writes connection-state directly from the
 * shared-memory Swoole\Table (via the Connection business object), so there is
 * no longer any IPC round-trip to the Connection helper process. Mirrors the
 * Server::setXxx / getXxx / clearXxx static API so existing callers keep working.
 */

namespace Yew\Plugins\Connection;

trait GetConnection
{
    /**
     * Cached Connection plugin configuration (process name, table sizes, etc.).
     * @var ConnectionConfig|null
     */
    protected ?ConnectionConfig $connectionConfig = null;

    /**
     * Lazily resolve and return the ConnectionConfig instance from the DI container.
     *
     * @return ConnectionConfig|null
     */
    protected function getConnectionConfig(): ?ConnectionConfig
    {
        if ($this->connectionConfig == null) {
            $this->connectionConfig = DIGet(ConnectionConfig::class);
        }
        return $this->connectionConfig;
    }

    /**
     * Store a key/value pair for a connection fd (local shared-memory write).
     */
    public function setFdSession(int $fd, string $key, mixed $value): void
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (!empty($conn)) {
            $conn->setFdSession($fd, $key, $value);
        }
    }

    /**
     * Resolve a value stored for a connection fd by key (local shared-memory read).
     */
    public function getFdSession(int $fd, string $key = 'uid')
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (empty($conn)) {
            return null;
        }
        return $conn->getFdSession($fd, $key);
    }

    /**
     * Store multiple key/value pairs for a connection fd (local shared-memory write).
     */
    public function setFdSessionMulti(int $fd, array $data): void
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (!empty($conn)) {
            $conn->setFdSessionMulti($fd, $data);
        }
    }

    /**
     * Resolve all values stored for a connection fd (local shared-memory read).
     */
    public function getFdSessionMulti(int $fd): ?array
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (empty($conn)) {
            return null;
        }
        return $conn->getFdSessionMulti($fd);
    }

    /**
     * Clear all fd-level session state (local shared-memory delete).
     */
    public function clearFdSession(int $fd): void
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (!empty($conn)) {
            $conn->clearFdSession($fd);
        }
    }

    /**
     * Store a key/value pair for a clientId (local shared-memory write).
     */
    public function setClientSession(string $clientId, string $key, $value): void
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (!empty($conn)) {
            $conn->setClientSession($clientId, $key, $value);
        }
    }

    /**
     * Resolve a value stored for a clientId by key (local shared-memory read).
     */
    public function getClientSession(string $clientId, string $key = 'uid')
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (empty($conn)) {
            return null;
        }
        return $conn->getClientSession($clientId, $key);
    }

    /**
     * Store multiple key/value pairs for a clientId (local shared-memory write).
     */
    public function setClientSessionMulti(string $clientId, array $data = []): void
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (!empty($conn)) {
            $conn->setClientSessionMulti($clientId, $data);
        }
    }

    /**
     * Resolve all values stored for a clientId (local shared-memory read).
     */
    public function getClientSessionMulti(string $clientId): ?array
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (empty($conn)) {
            return null;
        }
        return $conn->getClientSessionMulti($clientId);
    }

    /**
     * Clear all client-level session state (local shared-memory delete).
     */
    public function clearClientSession(string $clientId): void
    {
        /** @var Connection $conn */
        $conn = DIGet(Connection::class);
        if (!empty($conn)) {
            $conn->clearClientSession($clientId);
        }
    }
}
