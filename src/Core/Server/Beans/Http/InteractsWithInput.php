<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Beans\Http;

trait InteractsWithInput
{

    /**
     * Retrieve a server variable from the request
     *
     * @param null|string $key
     * @param null|mixed $default
     * @return array|string|mixed
     */
    public function server(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->getServerParams();
        }
        return $this->getServerParams()[$key] ?? $default;
    }

    /**
     * Retrieve a header from the request
     *
     * @param null|string $key
     * @param null|mixed $default
     * @return array|string|mixed
     */
    public function header(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->getHeaders();
        }
        return $this->getHeader($key) ?? $default;
    }

    /**
     * Retrieve a query string from the request
     *
     * @param null|string $key
     * @param null|mixed $default
     * @return array|string|mixed
     */
    public function query(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->getQueryParams();
        }
        return $this->getQueryParams()[$key] ?? $default;
    }

    /**
     * Retrieve a post item from the request
     *
     * @param null|string $key
     * @param null|mixed $default
     * @return array|string|mixed
     */
    public function post(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->getParsedBody();
        }
        return $this->getParsedBody()[$key] ?? $default;
    }

    /**
     * Retrieve an input item from the request
     *
     * @param null|string $key
     * @param null|mixed $default
     * @return array|string|mixed
     */
    public function input(?string $key = null, $default = null)
    {
        $inputs = $this->getQueryParams() + $this->getParsedBody();
        if (is_null($key)) {
            return $inputs;
        }
        return $inputs[$key] ?? $default;
    }

    /**
     * Retrieve a cookie from the request
     *
     * @param null|string $key
     * @param null|mixed $default
     * @return array|string|mixed
     */
    public function cookie(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->getCookieParams();
        }
        return $this->getCookieParams()[$key] ?? $default;
    }

    /**
     * Retrieve raw body from the request
     *
     * @param null|mixed $default
     * @return array|string|mixed
     */
    public function raw($default = null)
    {
        $body = $this->getBody();
        $raw = $default;
        if ($body instanceof HttpStream) {
            $raw = $body->getContents();
        }
        return $raw;
    }

    /**
     * Retrieve an upload item from the request
     *
     * @param string|null $key
     * @param null $default
     * @return array|null
     */
    public function file(string $key = null, $default = null): ?array
    {
        if (is_null($key)) {
            return $this->getUploadedFiles();
        }
        return $this->getUploadedFiles()[$key] ?? $default;
    }
}
