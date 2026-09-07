<?php
namespace Yew\Plugins\Mqtt\Connection;

class MqttConnection
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
    /**
     * Will message state: clientId -> will packet.
     * Keys: topic, payload, qos, retain, will_delay_interval, protocol_level.
     * @var array<string, array<string, mixed>>
     */
    protected array $wills = [];

    /**
     * Keepalive sweeper started flag (lazy, started once inside the Connection process).
     * @var bool
     */
    private bool $keepaliveTimerStarted = false;

    /**
     * MQTT keepalive grace factor: a connection is broken if no packet arrives
     * within 1.5 * keepAlive seconds (per spec).
     */
    private const KEEPALIVE_GRACE_FACTOR = 1.5;

    /**
     * How often the keepalive sweeper runs, in milliseconds.
     */
    private const KEEPALIVE_SWEEP_INTERVAL_MS = 5000;

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
     * Resolve a value stored for a connection fd by key.
     */
    public function getFdSession(int $fd, string $key = 'uid')
    {
        return $this->fdSession[$fd][$key] ?? null;
    }

    /**
     * Store multiple key/value pairs for a connection fd,
     * e.g. setFdSessionMulti($fd, ['uid' => $uid, 'session_start' => $flag]).
     */
    public function setFdSessionMulti(int $fd, array $data): void
    {
        foreach ($data as $key => $value) {
            $this->setFdSession($fd, $key, $value);
        }
    }

    /**
     * Resolve all values stored for a connection fd.
     */
    public function getFdSessionMulti(int $fd): ?array
    {
        return $this->fdSession[$fd] ?? null;
    }

    /**
     * Remove the entire session state for a connection fd.
     */
    public function clearFdSession(int $fd): void
    {
        unset($this->fdSession[$fd]);
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
     * Resolve a value stored for a clientId by key.
     */
    public function getClientSession(string $clientId, string $key = 'uid')
    {
        return $this->clientSession[$clientId][$key] ?? null;
    }

    /**
     * Store multiple key/value pairs for a clientId,
     * e.g. setClientSessionMulti($clientId, ['uid' => $uid, 'session_start' => $flag]).
     */
    public function setClientSessionMulti(string $clientId, array $data = []): void
    {
        foreach ($data as $key => $value) {
            $this->setClientSession($clientId, $key, $value);
        }
    }

    /**
     * @param string $clientId
     * @return mixed[]|null
     */
    public function getClientSessionMulti(string $clientId): ?array
    {
        return $this->clientSession[$clientId] ?? null;
    }

    /**
     * Remove the entire session state for a clientId.
     */
    public function clearClientSession(string $clientId): void
    {
        unset($this->clientSession[$clientId]);
    }

    /**
     * Record (or refresh) the last activity time for a connection fd and make
     * sure the keepalive sweeper is running. Called on every inbound MQTT packet.
     */
    public function touchActivity(int $fd): void
    {
        if (!isset($this->fdSession[$fd])) {
            $this->fdSession[$fd] = [];
        }
        $this->fdSession[$fd]['last_activity_at'] = microtime(true);
        $this->ensureKeepaliveTimer();
    }

    /**
     * Store the negotiated keepalive (seconds) for a connection and mark activity.
     * keepAlive <= 0 disables keepalive enforcement for this fd.
     */
    public function setKeepAlive(int $fd, int $keepAlive): void
    {
        if ($keepAlive > 0) {
            if (!isset($this->fdSession[$fd])) {
                $this->fdSession[$fd] = [];
            }
            $this->fdSession[$fd]['keep_alive'] = $keepAlive;
        }
        $this->touchActivity($fd);
    }

    /**
     * Register a Will message for a client (MQTT 5). Overwrites any previous will.
     *
     * @param string $clientId
     * @param array<string, mixed> $will keys: topic, payload, qos, retain, will_delay_interval, protocol_level
     */
    public function registerWill(string $clientId, array $will): void
    {
        $this->wills[$clientId] = $will;
    }

    /**
     * Peek the pending Will for a client without consuming it (used at close time
     * and again when a delayed-Will timer fires, so a reconnect can still cancel it).
     */
    public function getWill(string $clientId): ?array
    {
        return $this->wills[$clientId] ?? null;
    }

    /**
     * Cancel (clear) the pending Will for a client. Called on a normal DISCONNECT
     * and on reconnect (same clientId takes over the session -> MQTT 5 semantics).
     */
    public function cancelWill(string $clientId): void
    {
        unset($this->wills[$clientId]);
    }

    /**
     * Start the keepalive sweeper once, inside the Connection process event loop.
     */
    private function ensureKeepaliveTimer(): void
    {
        if ($this->keepaliveTimerStarted) {
            return;
        }
        $this->keepaliveTimerStarted = true;
        \Swoole\Timer::tick(self::KEEPALIVE_SWEEP_INTERVAL_MS, [$this, 'sweepKeepalive']);
    }

    /**
     * Scan all tracked fds; close any whose keepalive grace period has elapsed.
     *
     * Closing the fd crosses into the owner worker and fires onWsClose there,
     * which is where the Will (if any) is published. The fd session itself is
     * cleared by the worker-side MqttWebsocketController::onWsClose, so we only close here.
     */
    public function sweepKeepalive(): void
    {
        $now = microtime(true);
        foreach ($this->fdSession as $fd => $state) {
            $keepAlive = $state['keep_alive'] ?? 0;
            if ($keepAlive <= 0) {
                continue;
            }
            $last = $state['last_activity_at'] ?? $now;
            if (($now - $last) > $keepAlive * self::KEEPALIVE_GRACE_FACTOR) {
                \Yew\Core\Server\Server::$instance->closeFd($fd);
            }
        }
    }
}
