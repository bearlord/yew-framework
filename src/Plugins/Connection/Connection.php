<?php
/**
 * Yew framework - Connection plugin
 *
 * In-memory store of connection-level routing state, living inside the
 * Connection helper process. All mutating/reading calls arrive here via IPC
 * from worker processes through the GetConnection trait.
 *
 * Stored relations:
 *  - fd      -> uid          (connection file descriptor to subscriber uid)
 *  - clientId-> uid          (mqtt client identifier to subscriber uid)
 *  - clientId-> session_start(clean_session / clean_start flag)
 */

namespace Yew\Plugins\Connection;

class Connection
{
    /**
     * fd -> [key => value] mapping (in-memory, lives in the Connection helper process).
     * @var array<int, array<string, mixed>>
     */
    protected array $fdSession = [];

    /**
     * clientId -> [key => value] mapping.
     * @var array<string, array<string, mixed>>
     */
    protected array $clientSession = [];

    /**
     * Connection constructor.
     *
     * The instance is created once per helper process and kept alive for the
     * whole process lifetime (via DI container), so plain array properties are
     * safe and persist across IPC calls without needing Swoole\Table.
     */
    public function __construct()
    {
    }

    /**
     * Store a key/value pair for a connection fd, e.g. setFdSession($fd, 'uid', $uid).
     */
    public function setFdSession(int $fd, string $key, $value): void
    {
        $this->fdSession[$fd][$key] = $value;
    }

    /**
     * Store a key/value pair for a clientId, e.g. setClientSession($clientId, 'uid', $uid)
     * or setClientSession($clientId, 'session_start', $flag).
     */
    public function setClientSession(string $clientId, string $key, $value): void
    {
        $this->clientSession[$clientId][$key] = $value;
    }

    /**
     * Resolve a value stored for a connection fd by key.
     */
    public function getFdSession(int $fd, string $key = 'uid')
    {
        return $this->fdSession[$fd][$key] ?? null;
    }

    /**
     * Resolve a value stored for a clientId by key.
     */
    public function getClientSession(string $clientId, string $key = 'uid')
    {
        return $this->clientSession[$clientId][$key] ?? null;
    }

    /**
     * Remove the entire session state for a connection fd.
     */
    public function clearFdSession(int $fd): void
    {
        unset($this->fdSession[$fd]);
    }

    /**
     * Remove the entire session state for a clientId.
     */
    public function clearClientSession(string $clientId): void
    {
        unset($this->clientSession[$clientId]);
    }
}
