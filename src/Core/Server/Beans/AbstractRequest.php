<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Beans;

use Yew\Core\Server\Beans\Http\InteractsWithInput;
use Yew\Core\Server\Beans\Http\MessageTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

abstract class AbstractRequest implements RequestInterface, ServerRequestInterface
{
    const HEADER_HOST = "host";
    const HEADER_CONNECTION = "connection";
    const HEADER_PRAGMA = "pragma";
    const HEADER_CACHE_CONTROL = "cache-control";
    const HEADER_USER_AGENT = "user-agent";
    const HEADER_ACCEPT = "accept";
    const HEADER_REFERER = "referer";
    const HEADER_ACCEPT_ENCODING = "accept-encoding";
    const HEADER_ACCEPT_LANGUAGE = "accept-language";

    const SERVER_REQUEST_METHOD = "request_method";
    const SERVER_REQUEST_URI = "request_uri";
    const SERVER_PATH_INFO = "path_info";
    const SERVER_REQUEST_TIME = "request_time";
    const SERVER_REQUEST_TIME_FLOAT = "request_time_float";
    const SERVER_SERVER_PORT = "server_port";
    const SERVER_REMOTE_ADDR = "remote_addr";
    const SERVER_MASTER_TIME = "master_time";
    const SERVER_SERVER_PROTOCOL = "server_protocol";

    use MessageTrait;

    use InteractsWithInput;

    /**
     * @var array
     */
    protected array $server = [];

    /**
     * @var UriInterface
     */
    protected UriInterface $uri;

    /**
     * Http Method
     *
     * @var string
     */
    protected string $method;

    /**
     * @var string|null
     */
    protected ?string $requestTarget = null;

    /**
     * @var array
     */
    protected array $attributes = [];

    /**
     * @var array
     */
    protected array $cookieParams = [];

    /**
     * @var array|mixed|null
     */
    protected $parsedBody;

    /**
     * @var array
     */
    protected array $queryParams = [];

    /**
     * @var array
     */
    protected array $uploadedFiles = [];

    /**
     * @var int
     */
    protected int $fd = 0;

    /**
     * @var int
     */
    protected int $streamId = 0;

    /**
     * @var array
     */
    protected array $files;

    /**
     * @param string $name
     * @param null $default
     * @return mixed|null
     */
    public function getServer(string $name, $default = null)
    {
        return $this->server[$name] ?? $default;
    }

    /**
     * @return array
     */
    public function getServers(): array
    {
        return $this->server;
    }

    /**
     * Retrieves the message's request target.
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target == '') {
            $target = '/';
        }
        if ($this->uri->getQuery() != '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    /**
     * Return an instance with the specific request-target.
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.3 (for the various request-target forms allowed in request messages)
     * @param string|null $requestTarget
     * @return $this
     */
    public function withRequestTarget(?string $requestTarget = null): AbstractRequest
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Return an instance with the provided HTTP method.
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-sensitive method.
     * @return $this
     * @throws \InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod(string $method): AbstractRequest
    {
        $method = strtoupper($method);
        $methods = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'HEAD'];
        if (! in_array($method, $methods)) {
            throw new \InvalidArgumentException('Invalid Method');
        }
        $this->method = $method;
        return $this;
    }

    /**
     * Retrieve server parameters.
     * Retrieves data related to the incoming request environment,
     * typically derived from PHP's $_SERVER superglobal. The data IS NOT
     * REQUIRED to originate from $_SERVER.
     *
     * @return array
     */
    public function getServerParams(): array
    {
        return $this->server;
    }

    /**
     * Return an instance with the specified server params.
     *
     * @param array $serverParams
     * @return $this
     */
    public function withServerParams(array $serverParams): AbstractRequest
    {
        $this->server = $serverParams;
        return $this;
    }

    /**
     * Retrieve cookies.
     * Retrieves cookies sent by the client to the server.
     * The data MUST be compatible with the structure of the $_COOKIE
     * superglobal.
     *
     * @return array
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * Return an instance with the specified cookies.
     * The data IS NOT REQUIRED to come from the $_COOKIE superglobal, but MUST
     * be compatible with the structure of $_COOKIE. Typically, this data will
     * be injected at instantiation.
     * This method MUST NOT update the related Cookie header of the request
     * instance, nor related values in the server params.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated cookie values.
     *
     * @param array $cookies Array of key/value pairs representing cookies.
     * @return $this
     */
    public function withCookieParams(array $cookies): AbstractRequest
    {
        $this->cookieParams = $cookies;
        return $this;
    }

    /**
     * Retrieve query string arguments.
     * Retrieves the deserialized query string arguments, if any.
     * Note: the query params might not be in sync with the URI or server
     * params. If you need to ensure you are only getting the original
     * values, you may need to parse the query string from `getUri()->getQuery()`
     * or from the `QUERY_STRING` server param.
     *
     * @return array
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * add param
     *
     * @param string $name  the name of param
     * @param mixed  $value the value of param
     *
     * @return $this
     */
    public function addQueryParam(string $name, $value): AbstractRequest
    {
        $this->queryParams[$name] = $value;

        return $this;
    }

    /**
     * Return an instance with the specified query string arguments.
     * These values SHOULD remain immutable over the course of the incoming
     * request. They MAY be injected during instantiation, such as from PHP's
     * $_GET superglobal, or MAY be derived from some other value such as the
     * URI. In cases where the arguments are parsed from the URI, the data
     * MUST be compatible with what PHP's parse_str() would return for
     * purposes of how duplicate query parameters are handled, and how nested
     * sets are handled.
     * Setting query string arguments MUST NOT change the URI stored by the
     * request, nor the values in the server params.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated query string arguments.
     *
     * @param array $query Array of query string arguments, typically from $_GET.
     * @return $this
     */
    public function withQueryParams(array $query): AbstractRequest
    {
        $this->queryParams = $query;
        return $this;
    }

    /**
     * Retrieve normalized file upload data.
     * This method returns upload metadata in a normalized tree, with each leaf
     * an instance of Psr\Http\Message\UploadedFileInterface.
     * These values MAY be prepared from $_FILES or the message body during
     * instantiation, or MAY be injected via withUploadedFiles().
     *
     * @return array An array tree of UploadedFileInterface instances; an empty
     *     array MUST be returned if no data is present.
     */
    public function getUploadedFiles(): array
    {
        return $this->files;
    }

    /**
     * Create a new instance with the specified uploaded files.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated body parameters.
     *
     * @param array $uploadedFiles An array tree of UploadedFileInterface instances.
     * @return $this
     * @throws \InvalidArgumentException if an invalid structure is provided.
     */
    public function withUploadedFiles(array $uploadedFiles): AbstractRequest
    {
        $this->files = $uploadedFiles;
        return $this;
    }

    /**
     * Retrieve any parameters provided in the request body.
     * If the request Content-Type is either application/x-www-form-urlencoded
     * or multipart/form-data, and the request method is POST, this method MUST
     * return the contents of $_POST.
     * Otherwise, this method may return any results of deserializing
     * the request body content; as parsing returns structured content, the
     * potential types MUST be arrays or objects only. A null value indicates
     * the absence of body content.
     *
     * @return null|array|object The deserialized body parameters, if any. These will typically be an array or object.
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * add parser body
     *
     * @param string $name  the name of param
     * @param mixed  $value the value of param
     *
     * @return $this
     */
    public function addParserBody(string $name, $value): AbstractRequest
    {
        if (is_array($this->parsedBody)) {
            $this->parsedBody[$name] = $value;
            return $this;
        }
        return $this;
    }

    /**
     * Return an instance with the specified body parameters.
     * These MAY be injected during instantiation.
     * If the request Content-Type is either application/x-www-form-urlencoded
     * or multipart/form-data, and the request method is POST, use this method
     * ONLY to inject the contents of $_POST.
     * The data IS NOT REQUIRED to come from $_POST, but MUST be the results of
     * deserializing the request body content. Deserialization/parsing returns
     * structured data, and, as such, this method ONLY accepts arrays or objects,
     * or a null value if nothing was available to parse.
     * As an example, if content negotiation determines that the request data
     * is a JSON payload, this method could be used to create a request
     * instance with the deserialized parameters.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated body parameters.
     *
     * @param null|array|object $data The deserialized body data. This will typically be in an array or object.
     * @return $this
     * @throws \InvalidArgumentException if an unsupported argument type is provided.
     */
    public function withParsedBody($data): AbstractRequest
    {
        $this->parsedBody = $data;
        return $this;
    }

    /**
     * Retrieve attributes derived from the request.
     * The request "attributes" may be used to allow injection of any
     * parameters derived from the request: e.g., the results of path
     * match operations; the results of decrypting cookies; the results of
     * deserializing non-form-encoded message bodies; etc. Attributes
     * will be application and request specific, and CAN be mutable.
     *
     * @return array Attributes derived from the request.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Retrieve a single derived request attribute.
     * Retrieves a single derived request attribute as described in
     * getAttributes(). If the attribute has not been previously set, returns
     * the default value as provided.
     * This method obviates the need for a hasAttribute() method, as it allows
     * specifying a default value to return if the attribute is not found.
     *
     * @param string $name    The attribute name.
     * @param mixed  $default Default value to return if the attribute does not exist.
     * @return mixed
     *@see getAttributes()
     */
    public function getAttribute(string $name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * Return an instance with the specified derived request attribute.
     * This method allows setting a single derived request attribute as
     * described in getAttributes().
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated attribute.
     *
     * @param string $name  The attribute name.
          * @param mixed  $value The value of the attribute.
     * @return $this
     *@see getAttributes()
     */
    public function withAttribute(string $name, $value): AbstractRequest
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * Return an instance that removes the specified derived request attribute.
     * This method allows removing a single derived request attribute as
     * described in getAttributes().
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the attribute.
     *
     * @param string $name The attribute name.
          * @return $this
     *@see getAttributes()
     */
    public function withoutAttribute(string $name): AbstractRequest
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $this;
        }

        unset($this->attributes[$name]);

        return $this;
    }

    /**
     * Retrieves the URI instance.
     * This method MUST return a UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Returns an instance with the provided URI.
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     * - If the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param UriInterface $uri          New request URI to use.
     * @param bool|null $preserveHost Preserve the original state of the Host header.
     * @return $this
     */
    public function withUri(UriInterface $uri, ?bool $preserveHost = false): AbstractRequest
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $this->uri = $uri;

        if (! $preserveHost) {
            $this->updateHostFromUri();
        }

        return $this;
    }

    /**
     * Update Host Header according to Uri
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.4
     */
    protected function updateHostFromUri()
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        if ($this->hasHeader('host')) {
            $header = $this->getHeaderLine('host');
        } else {
            $header = 'Host';
            $this->headerNames['host'] = 'Host';
        }
        // Ensure Host is the first header.
        $this->headers = [$header => [$host]] + $this->headers;
    }

    /**
     * Load
     * @param null $realObject
     * @return mixed
     */
    abstract public function load($realObject = null);

    /**
     * @return int
     */
    public function getFd(): int
    {
        return $this->fd;
    }

    /**
     * @param int $fd
     */
    public function setFd(int $fd): void
    {
        $this->fd = $fd;
    }

    /**
     * @return int
     */
    public function getStreamId(): int
    {
        return $this->streamId;
    }

    /**
     * @param int $streamId
     */
    public function setStreamId(int $streamId): void
    {
        $this->streamId = $streamId;
    }

    /**
     * @return array
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @param array $files
     */
    public function setFiles(array $files): void
    {
        $this->files = $files;
    }
}
