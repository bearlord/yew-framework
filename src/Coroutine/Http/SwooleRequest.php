<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Http;

use Yew\Core\Exception\Exception;
use Yew\Core\Exception\ParamException;
use Yew\Core\Server\Beans\Http\HttpStream;
use Yew\Core\Server\Beans\Http\Uri;
use Yew\Core\Server\Beans\Request;

/**
 * Class SwooleRequest
 * @package Yew\Server\Coroutine\Http
 */
class SwooleRequest extends Request
{

    /**
     * @var \Swoole\Http\Request
     */
    protected $swooleRequest;

    /**
     * @param null $realObject
     * @throws Exception
     */
    public function load($realObject = null)
    {
        if (!$realObject instanceof \Swoole\Http\Request) {
            throw new Exception("object must be instance of Swoole\\Request");
        }
        $this->swooleRequest = $realObject;
        $this->setHeaders($this->swooleRequest->header ?? []);
        $this->server = $this->swooleRequest->server;

        $this->queryParams = $this->swooleRequest->get ?? [];
        $this->parsedBody = $this->swooleRequest->post ?? [];

        $this->cookieParams = $this->swooleRequest->cookie ?? [];

        $this->files = $this->swooleRequest->files ?? [];
        $this->fd = $this->swooleRequest->fd;

        $this->streamId = property_exists($this->swooleRequest, "streamId") ? $this->swooleRequest->streamId : 0;

        $this->stream = new HttpStream($this->swooleRequest->rawContent());

        $this->method = strtoupper($this->server[self::SERVER_REQUEST_METHOD]);
        $queryString = '';
        if (!empty($this->queryParams)) {
            $queryString = "?" . http_build_query($this->queryParams);
        }

        if (empty($this->headers['host'][0])) {
            throw new ParamException(sprintf( "%s %s Headers parsing error. Headers: %s", $this->server['request_method'], $this->server['request_uri'], json_encode($this->headers, JSON_UNESCAPED_SLASHES)));
        }

        $this->uri = new Uri(sprintf("%s://%s%s%s",
                $this->getScheme(),
                $this->headers['host'][0],
                $this->server[self::SERVER_REQUEST_URI],
                $queryString)
        );
    }
}