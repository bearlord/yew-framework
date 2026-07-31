<?php
/**
 * Yew framework - Connection plugin
 *
 * Trait used by worker-side code to forward connection-state calls to the
 * Connection helper process via IPC. Mirrors the Server::setXxx / getXxx /
 * clearXxx static API so existing callers can be redirected with minimal change.
 */

namespace Yew\Plugins\Connection;

use Yew\Plugins\Ipc\GetIpc;

trait GetConnection
{
    use GetIpc;

    /**
     * Cached Connection plugin configuration (process name, etc.).
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
     * Store a key/value pair for a connection fd on the Connection process,
     * e.g. setFdSession($fd, 'uid', $uid).
     */
    public function setFdSession(int $fd, string $key, $value): void
    {
        /** @var Connection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getConnectionConfig()->getProcessName(), Connection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->setFdSession($fd, $key, $value);
        }
    }

    /**
     * Store a key/value pair for a clientId on the Connection process,
     * e.g. setClientSession($clientId, 'uid', $uid) or
     * setClientSession($clientId, 'session_start', $flag).
     */
    public function setClientSession(string $clientId, string $key, $value): void
    {
        /** @var Connection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getConnectionConfig()->getProcessName(), Connection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->setClientSession($clientId, $key, $value);
        }
    }

    /**
     * Resolve a value stored for a connection fd by key (defaults to 'uid').
     */
    public function getFdSession(int $fd, string $key = 'uid')
    {
        /** @var Connection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getConnectionConfig()->getProcessName(), Connection::class);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getFdSession($fd, $key);
    }

    /**
     * Resolve a value stored for a clientId by key (defaults to 'uid').
     */
    public function getClientSession(string $clientId, string $key = 'uid')
    {
        /** @var Connection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getConnectionConfig()->getProcessName(), Connection::class);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getClientSession($clientId, $key);
    }

    /**
     * Clear all fd-level session state on the Connection process.
     */
    public function clearFdSession(int $fd): void
    {
        /** @var Connection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getConnectionConfig()->getProcessName(), Connection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->clearFdSession($fd);
        }
    }

    /**
     * Clear all client-level session state on the Connection process.
     */
    public function clearClientSession(string $clientId): void
    {
        /** @var Connection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getConnectionConfig()->getProcessName(), Connection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->clearClientSession($clientId);
        }
    }
}
