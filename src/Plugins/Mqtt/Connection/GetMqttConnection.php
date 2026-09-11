<?php

namespace Yew\Plugins\Mqtt\Connection;

use Yew\Plugins\Ipc\GetIpc;

trait GetMqttConnection
{
    use GetIpc;

    /**
     * Cached Connection plugin configuration (process name, etc.).
     * @var MqttConnectionConfig|null
     */
    protected ?MqttConnectionConfig $mqttConnectionConfig = null;

    /**
     * Lazily resolve and return the MqttConnectionConfig instance from the DI container.
     *
     * @return MqttConnectionConfig|null
     */
    protected function getMqttConnectionConfig(): ?MqttConnectionConfig
    {
        if ($this->mqttConnectionConfig == null) {
            $this->mqttConnectionConfig = DIGet(MqttConnectionConfig::class);
        }
        return $this->mqttConnectionConfig;
    }

    /**
     * Store a key/value pair for a connection fd on the Connection process,
     * e.g. setFdSession($fd, 'uid', $uid).
     */
    public function setFdSession(int $fd, string $key, mixed $value): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->setFdSession($fd, $key, $value);
        }
    }

    /**
     * Resolve a value stored for a connection fd by key (defaults to 'uid').
     */
    public function getFdSession(int $fd, string $key = 'uid')
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getFdSession($fd, $key);
    }

    /**
     * Store multiple key/value pairs for a connection fd on the Connection process,
     * e.g. setFdSessionMulti($fd, ['uid' => $uid, 'session_start' => $flag]).
     */
    public function setFdSessionMulti(int $fd, array $data): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->setFdSessionMulti($fd, $data);
        }
    }

    /**
     * Resolve all values stored for a connection fd (returns the full map or null).
     */
    public function getFdSessionMulti(int $fd): ?array
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getFdSessionMulti($fd);
    }

    /**
     * Clear all fd-level session state on the Connection process.
     */
    public function clearFdSession(int $fd): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->clearFdSession($fd);
        }
    }

    /**
     * Store a key/value pair for a clientId on the Connection process,
     * e.g. setClientSession($clientId, 'uid', $uid) or
     * setClientSession($clientId, 'session_start', $flag).
     */
    public function setClientSession(string $clientId, string $key, $value): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->setClientSession($clientId, $key, $value);
        }
    }

    /**
     * Resolve a value stored for a clientId by key (defaults to 'uid').
     */
    public function getClientSession(string $clientId, string $key = 'uid')
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getClientSession($clientId, $key);
    }

    /**
     * Store multiple key/value pairs for a clientId on the Connection process,
     * e.g. setClientSessionMulti($clientId, ['uid' => $uid, 'session_start' => $flag]).
     */
    public function setClientSessionMulti(string $clientId, array $data = []): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->setClientSessionMulti($clientId, $data);
        }
    }

    /**
     * Resolve all values stored for a clientId (returns the full map or null).
     */
    public function getClientSessionMulti(string $clientId): ?array
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getClientSessionMulti($clientId);
    }

    /**
     * Clear all client-level session state on the Connection process.
     */
    public function clearClientSession(string $clientId): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->clearClientSession($clientId);
        }
    }

    /**
     * Refresh the keepalive activity timestamp for a connection fd.
     * @see MqttConnection::touchActivity()
     */
    public function touchActivity(int $fd): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->touchActivity($fd);
        }
    }

    /**
     * Store the negotiated keepalive (seconds) for a connection fd.
     * @see MqttConnection::setKeepAlive()
     */
    public function setKeepAlive(int $fd, int $keepAlive): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->setKeepAlive($fd, $keepAlive);
        }
    }

    /**
     * Register a Will message for a client.
     * @see MqttConnection::registerWill()
     */
    public function registerWill(string $clientId, array $will): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->registerWill($clientId, $will);
        }
    }

    /**
     * Peek the pending Will for a client (without consuming it).
     * @see MqttConnection::getWill()
     * @return array<string, mixed>|null
     */
    public function getWill(string $clientId): ?array
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getWill($clientId);
    }

    /**
     * Cancel (clear) the pending Will for a client.
     * @see MqttConnection::cancelWill()
     */
    public function cancelWill(string $clientId): void
    {
        /** @var MqttConnection $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMqttConnectionConfig()->getProcessName(), MqttConnection::class, true);
        if (!empty($ipcProxy)) {
            $ipcProxy->cancelWill($clientId);
        }
    }
}
