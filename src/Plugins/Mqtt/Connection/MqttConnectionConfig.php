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
