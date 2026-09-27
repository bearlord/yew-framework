<?php
/**
 * Yew framework - Connection plugin
 *
 * Stores connection-level routing state (fd <-> uid, clientId <-> uid,
 * clientId <-> session_start) inside a dedicated helper process so the data
 * survives worker restarts (unlike Server's static properties).
 */

namespace Yew\Plugins\Mqtt\Connection;

use Yew\Core\Plugins\Config\BaseConfig;

class MqttConnectionConfig extends BaseConfig
{
    const KEY = "mqtt-connection";

    /**
     * @var string  Process group name for the mqtt connection process.
     */
    protected string $processGroupName = "HelperGroup";

    /**
     * @var string Process name for the mqtt connection process.
     */
    protected string $processName = "mqtt-connection";

    /**
     * @var bool Whether to fan MQTT publishes out to other cluster nodes.
     */
    protected bool $clusterEnabled = false;

    /**
     * @var int Dedicated UDP port for MQTT cluster fan-out (0 = disabled).
     */
    protected int $clusterPort = 0;

    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return bool
     */
    public function isClusterEnabled(): bool
    {
        return $this->clusterEnabled;
    }

    /**
     * @param bool $clusterEnabled
     */
    public function setClusterEnabled(bool $clusterEnabled): void
    {
        $this->clusterEnabled = $clusterEnabled;
    }

    /**
     * @return int
     */
    public function getClusterPort(): int
    {
        return $this->clusterPort;
    }

    /**
     * @param int $clusterPort
     */
    public function setClusterPort(int $clusterPort): void
    {
        $this->clusterPort = $clusterPort;
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

    /**
     * @return string
     */
    public function getProcessGroupName(): string
    {
        return $this->processGroupName;
    }

    /**
     * @param string $processGroupName
     */
    public function setProcessGroupName(string $processGroupName): void
    {
        $this->processGroupName = $processGroupName;
    }
}
