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

    /**
     * Max rows for the fd -> session map table.
     * @var int
     */
    protected int $fdTableSize = 65536;

    /**
     * Max rows for the clientId -> session map table.
     * @var int
     */
    protected int $clientTableSize = 65536;

    /**
     * Bytes per row (holds the serialized KV map).
     * @var int
     */
    protected int $dataColumnSize = 2048;

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

    public function getFdTableSize(): int
    {
        return $this->fdTableSize;
    }

    public function setFdTableSize(int $fdTableSize): void
    {
        $this->fdTableSize = $fdTableSize;
    }

    public function getClientTableSize(): int
    {
        return $this->clientTableSize;
    }

    public function setClientTableSize(int $clientTableSize): void
    {
        $this->clientTableSize = $clientTableSize;
    }

    public function getDataColumnSize(): int
    {
        return $this->dataColumnSize;
    }

    public function setDataColumnSize(int $dataColumnSize): void
    {
        $this->dataColumnSize = $dataColumnSize;
    }
}
