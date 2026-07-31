<?php
/**
 * Yew framework - Connection plugin
 *
 * Stores connection-level routing state (fd <-> uid, clientId <-> uid,
 * clientId <-> session_start) inside a dedicated helper process so the data
 * survives worker restarts (unlike Server's static properties).
 */

namespace Yew\Plugins\Connection;

use Yew\Core\Plugins\Config\BaseConfig;

class ConnectionConfig extends BaseConfig
{
    const KEY = "connection";

    /**
     * Helper process name that hosts the in-memory connection state.
     * @var string
     */
    protected string $processName = "connection";

    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return string
     */
    public function getProcessName(): string
    {
        return $this->processName;
    }

    /**
     * @param string $processName
     */
    public function setProcessName(string $processName): void
    {
        $this->processName = $processName;
    }
}
