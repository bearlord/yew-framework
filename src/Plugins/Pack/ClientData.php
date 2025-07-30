<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack;

use Yew\Core\Server\Beans\ClientInfo;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Coroutine\Server\Server;

class ClientData
{
    /**
     * @var string|null
     */
    protected ?string $controllerName = null;

    /**
     * @var string|null
     */
    protected ?string $requestMethod = null;
    /**
     * @var string|null
     */
    protected ?string $methodName = null;

    /**
     * @var ClientInfo
     */
    protected ClientInfo $clientInfo;
    /**
     * @var Request
     */
    protected ?Request $request = null;

    /**
     * @var Response
     */
    protected ?Response $response = null;

    /**
     * @var int
     */
    protected int $fd;

    /**
     * @var string|null
     */
    protected ?string $path = null;

    /**
     * @var array
     */
    protected array $params = [];

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var mixed
     */
    protected $responseRaw;

    /**
     * @var array Annotation
     */
    protected array $annotations = [];

    /**
     * ClientData constructor.
     * @param $fd
     * @param $requestMethod
     * @param $path
     * @param $data
     */
    public function __construct($fd, $requestMethod, $path, $data)
    {
        $this->setPath($path);
        $this->setData($data);
        $this->setFd($fd);
        $this->setRequestMethod($requestMethod);
    }

    /**
     * @return string|null
     */
    public function getControllerName(): ?string
    {
        return $this->controllerName;
    }

    /**
     * @param string $controllerName
     */
    public function setControllerName(?string $controllerName): void
    {
        $this->controllerName = $controllerName;
    }

    /**
     * @return string|null
     */
    public function getMethodName(): ?string
    {
        if ($this->methodName != null) {
            return $this->methodName;
        }
        return null;
    }

    /**
     * @param string $methodName
     */
    public function setMethodName(?string $methodName): void
    {
        $this->methodName = $methodName;
    }

    /**
     * @return string|null
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = "/" . trim($path, "/");
    }

    /**
     * @return array|null
     */
    public function getParams(): ?array
    {
        return $this->params;
    }

    /**
     * @param array|null $params
     */
    public function setParams(?array $params): void
    {
        $this->params = $params;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * @param Response $response
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    /**
     * @return ClientInfo
     */
    public function getClientInfo(): ClientInfo
    {
        return $this->clientInfo;
    }

    /**
     * @param int $fd
     */
    public function setFd(int $fd): void
    {
        $this->fd = $fd;
        if ($this->fd >= 0) {
            $this->clientInfo = Server::$instance->getClientInfo($fd);
        }
    }

    /**
     * @return string
     */
    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    /**
     * @param string $requestMethod
     */
    public function setRequestMethod(string $requestMethod): void
    {
        $this->requestMethod = $requestMethod;
    }

    /**
     * udp专用
     * @param array $clientInfo
     */
    public function setUdpClientInfo(array $clientInfo): void
    {
        $this->clientInfo = new ClientInfo(
            [
                "server_port" => $clientInfo['server_port'],
                "remote_ip" => $clientInfo['address'],
                "remote_port" => $clientInfo['port'],
            ]
        );
    }

    /**
     * @return int
     */
    public function getFd(): ?int
    {
        return $this->fd;
    }

    /**
     * @return mixed
     */
    public function getResponseRaw()
    {
        return $this->responseRaw;
    }

    /**
     * @param mixed $responseRaw
     */
    public function setResponseRaw($responseRaw): void
    {
        $this->responseRaw = $responseRaw;
    }

    /**
     * @return array
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * @param array $annotations
     */
    public function setAnnotations(array $annotations): void
    {
        $this->annotations = $annotations;
    }
}