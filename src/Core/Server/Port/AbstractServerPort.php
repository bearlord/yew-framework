<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Port;

use Yew\Core\Context\Context;
use Yew\Core\Exception\Exception;
use Yew\Core\Exception\ParamException;
use Yew\Core\Server\Beans\AbstractRequest;
use Yew\Core\Server\Beans\AbstractResponse;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Core\Server\Beans\WebSocketCloseFrame;
use Yew\Core\Server\Beans\WebSocketFrame;
use Yew\Core\Server\Config\PortConfig;
use Yew\Core\Server\Server;

abstract class AbstractServerPort
{
    /**
     * @var Context|null
     */
    protected ?Context $context;

    /**
     * @var PortConfig
     */
    private PortConfig $portConfig;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var \Swoole\Server\Port
     */
    private \Swoole\Server\Port $swoolePort;

    /**
     * AbstractServerPort constructor.
     *
     * @param Server $server
     * @param PortConfig $portConfig
     */
    public function __construct(Server $server, PortConfig $portConfig)
    {
        $this->portConfig = $portConfig;
        $this->server = $server;
        $this->context = $this->server->getContext();
    }

    /**
     * @return \Swoole\Server\Port
     */
    public function getSwoolePort(): \Swoole\Server\Port
    {
        return $this->swoolePort;
    }

    /**
     * @return void
     * @throws Exception\ConfigException
     */
    public function create(): void
    {
        if ($this->server->getMainPort() === $this) {
            //Swoole create port, get port instance
            $this->swoolePort = $this->server->getServer()->ports[0];
            //Listening is server
            $listening = $this->server->getServer();
        } else {
            $configData = $this->getPortConfig()->buildConfig();
            $this->swoolePort = $this->server->getServer()->listen(
                $this->getPortConfig()->getHost(),
                $this->getPortConfig()->getPort(),
                $this->getPortConfig()->getSwooleSockType()
            );

            $this->swoolePort->set($configData);

            //Listening is port instance
            $listening = $this->swoolePort;
        }

        //TCP
        if ($this->isTcp()) {
            $listening->on("connect", [$this, "_onConnect"]);
            $listening->on("close", [$this, "_onClose"]);
            $listening->on("receive", [$this, "_onReceive"]);
        }

        //UDP
        if ($this->isUDP()) {
            $listening->on("packet", [$this, "_onPacket"]);
        }

        //HTTP
        if ($this->isHttp()) {
            $listening->on("request", [$this, "_onRequest"]);
        }

        //WebSocket
        if ($this->isWebSocket()) {
            $listening->on("close", [$this, "_onClose"]);
            $listening->on("message", [$this, "_onMessage"]);
            $listening->on("open", [$this, "_onOpen"]);
            if ($this->getPortConfig()->isCustomHandShake()) {
                $listening->on("handshake", [$this, "_onHandshake"]);
            }
        }
    }

    /**
     * @return PortConfig
     */
    public function getPortConfig(): PortConfig
    {
        return $this->portConfig;
    }

    /**
     * Is TCP
     *
     * @return bool
     */
    public function isTcp(): bool
    {
        if ($this->isHttp()) return false;
        if ($this->isWebSocket()) return false;
        if ($this->getPortConfig()->getSockType() == PortConfig::SWOOLE_SOCK_TCP ||
            $this->getPortConfig()->getSockType() == PortConfig::SWOOLE_SOCK_TCP6) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Is HTTP
     *
     * @return bool
     */
    public function isHttp(): bool
    {
        return $this->getPortConfig()->isOpenHttpProtocol() || $this->getPortConfig()->isOpenWebsocketProtocol();
    }

    /**
     * Is websocket
     *
     * @return bool
     */
    public function isWebSocket(): bool
    {
        return $this->getPortConfig()->isOpenWebsocketProtocol();
    }

    /**
     * Is UDP
     *
     * @return bool
     */
    public function isUDP(): bool
    {
        if ($this->getPortConfig()->getSockType() == PortConfig::SWOOLE_SOCK_UDP ||
            $this->getPortConfig()->getSockType() == PortConfig::SWOOLE_SOCK_UDP6) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $server
     * @param int $fd
     * @param int $reactorId
     * @throws \Exception
     */
    public function _onConnect($server, int $fd, int $reactorId)
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();
        try {
            $this->onTcpConnect($fd, $reactorId);
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }
    }

    /**
     * @param int $fd
     * @param int $reactorId
     * @return mixed
     */
    public abstract function onTcpConnect(int $fd, int $reactorId);

    /**
     * @param $server
     * @param int $fd
     * @param int $reactorId
     * @throws \Exception
     */
    public function _onClose($server, int $fd, int $reactorId)
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();
        try {
            $port = Server::$instance->getPortManager()->getPortFromFd($fd);
            if (empty($port)) {
                return;
            }

            if ($port->isWebSocket()) {
                $this->onWsClose($fd, $reactorId);
            } elseif ($port->isUDP()) {
                $this->onTcpClose($fd, $reactorId);
            }
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }
    }

    public abstract function onTcpClose(int $fd, int $reactorId);

    public abstract function onWsClose(int $fd, int $reactorId);

    public function _onReceive($server, int $fd, int $reactorId, string $data)
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();
        try {
            $this->onTcpReceive($fd, $reactorId, $data);
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }
    }

    public abstract function onTcpReceive(int $fd, int $reactorId, string $data);

    /**
     * @param $server
     * @param string $data
     * @param array $clientInfo
     * @throws \Exception
     */
    public function _onPacket($server, string $data, array $clientInfo)
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();
        try {
            $this->onUdpPacket($data, $clientInfo);
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }
    }

    public abstract function onUdpPacket(string $data, array $clientInfo);

    /**
     * @param $request
     * @param $response
     * @return false|void
     * @throws \Exception
     */
    public function _onRequest($request, $response)
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();

        /**
         * @var $_response Response
         */
        $_response = DIGet(AbstractResponse::class);
        $_response->load($response);

        /**
         * @var $_request Request
         */
        $_request = DIGet(AbstractRequest::class);
        try {
            $_request->load($request);
        } catch (ParamException $exception) {
            Server::$instance->getLog()->error($exception->getMessage());

            $msg = '400 Bad Request';
            $_response->withStatus(400)->withContent($msg)->end();
            return false;
        } catch (Exception $exception) {
            Server::$instance->getLog()->error($exception->getMessage());
            return false;
        }
        try {
            setContextValueWithClass("request", $_request, Request::class);
            setContextValueWithClass("response", $_response, Response::class);
            $this->onHttpRequest($_request, $_response);
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }
        $_response->end();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public abstract function onHttpRequest(Request $request, Response $response);

    /**
     * @param $server
     * @param $frame
     * @throws \Exception
     */
    public function _onMessage($server, $frame)
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();
        try {
            if (isset($frame->code)) {
                $this->onWsMessage(new WebSocketCloseFrame($frame));
            } else {
                $this->onWsMessage(new WebSocketFrame($frame));
            }
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }
    }

    /**
     * @param WebSocketFrame $frame
     * @return mixed
     */
    public abstract function onWsMessage(WebSocketFrame $frame);

    /**
     * @param $request
     * @param $response
     * @return bool
     * @throws \Exception
     */
    public function _onHandshake($request, $response): bool
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();

        try {
            /**
             * @var $_request Request
             */
            $_request = DIGet(AbstractRequest::class);
            $_request->load($request);
            setContextValueWithClass("request", $_request, Request::class);
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }

        $success = $this->onWsPassCustomHandshake($_request);
        if (!$success) {
            return false;
        }

        //Handshake connection algorithm for authentication
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));
        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }
        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }
        $response->status(101);
        $response->end();

        \Swoole\Event::defer(function () use ($request) {
            \Swoole\Coroutine::create(function () use ($request) {
                $this->_onOpen($this->server->getServer(), $request);
            });
        });
        return true;
    }

    public abstract function onWsPassCustomHandshake(Request $request): bool;

    /**
     * @param $server
     * @param $request
     * @throws \Exception
     */
    public function _onOpen($server, $request)
    {
        Server::$instance->getProcessManager()->getCurrentProcess()->waitReady();
        try {
            /**
             * @var $_request Request
             */
            $_request = DIGet(AbstractRequest::class);
            $_request->load($request);
            setContextValueWithClass("request", $_request, Request::class);
            $this->onWsOpen($_request);
        } catch (\Throwable $e) {
            Server::$instance->getLog()->error($e);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public abstract function onWsOpen(Request $request);

}
