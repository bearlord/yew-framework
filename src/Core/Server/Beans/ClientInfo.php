<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Beans;

class ClientInfo
{
    /**
     * Reactor thread
     * @var int|null
     */
    private ?int $reactorId = null;

    /**
     * Server fd, not the fd of the client connection
     * @var int|null
     */
    private ?int $serverFd = null;

    /**
     * Server port
     * @var int|null
     */
    private ?int $serverPort = null;

    /**
     * Client port
     * @var int| null
     */
    private ?int $remotePort = null;

    /**
     * IP address of the client connection
     * @var string|null
     */
    private ?string $remoteIp = null;

    /**
     * Time for the client to connect to the server, in seconds, set by the master process
     * @var int|null
     */
    private ?int $connectTime = null;

    /**
     * Last time data was received, in seconds, set by the master process
     * @var int|null
     */
    private ?int $lastTime = null;

    /**
     * Connection close error code. If the connection is closed abnormally,
     * the value of close_errno is non-zero. You can refer to the Linux error message list.
     * @var int|null
     */
    private ?int $closeErrno = null;

    /**
     * [Optional] WebSocket connection status,
     * this information will be added when the server is Swoole\WebSocket\Server
     * @var int|null
     */
    private ?int $websocketStatus = null;

    /**
     * [Optional] Use SSL tunnel encryption and add this information when the client sets a certificate
     * @var string|null
     */
    private ?string $sslClientCert = null;

    /**
     * [Optional] This information will be added when bind user ID with bind
     * @var int|null
     */
    private ?int $uid = null;

    /**
     * ClientInfo constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->reactorId = $data['reactor_id'] ?? null;
        $this->serverFd = $data['server_fd'] ?? null;
        $this->serverPort = $data['server_port'] ?? null;
        $this->remotePort = $data['remote_port'] ?? null;
        $this->remoteIp = $data['remote_ip'] ?? null;
        $this->connectTime = $data['connect_time'] ?? null;
        $this->lastTime = $data['last_time'] ?? null;
        $this->closeErrno = $data['close_errno'] ?? null;
        $this->websocketStatus = $data['websocket_status'] ?? null;
        $this->sslClientCert = $data['ssl_client_cert'] ?? null;

        $this->uid = $data['uid'] ?? null;
    }

    /**
     * @return int
     */
    public function getReactorId(): ?int
    {
        return $this->reactorId;
    }

    /**
     * @return int
     */
    public function getServerFd(): ?int
    {
        return $this->serverFd;
    }

    /**
     * @return int
     */
    public function getServerPort(): ?int
    {
        return $this->serverPort;
    }

    /**
     * @return int
     */
    public function getRemotePort(): ?int
    {
        return $this->remotePort;
    }

    /**
     * @return int
     */
    public function getRemoteIp(): ?string
    {
        return $this->remoteIp;
    }

    /**
     * @return int
     */
    public function getConnectTime(): ?int
    {
        return $this->connectTime;
    }

    /**
     * @return int
     */
    public function getLastTime(): ?int
    {
        return $this->lastTime;
    }

    /**
     * @return int
     */
    public function getCloseErrno(): ?int
    {
        return $this->closeErrno;
    }

    /**
     * @return int
     */
    public function getWebsocketStatus(): ?int
    {
        return $this->websocketStatus;
    }

    /**
     * @return string
     */
    public function getSslClientCert(): ?string
    {
        return $this->sslClientCert;
    }

    /**
     * @return int
     */
    public function getUid(): ?int
    {
        return $this->uid;
    }
}