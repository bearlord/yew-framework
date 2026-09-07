<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;

use Yew\Core\Plugins\Config\BaseConfig;

/**
 * Session plugin configuration (bound to the "session" key in app config).
 */
class SessionConfig extends BaseConfig
{
    /** Config root key. */
    const KEY = "session";

    /** Session id carried in a cookie. */
    const USAGE_COOKIE = "cookie";

    /** Session id carried in a custom request header. */
    const USEAGE_HEADER = "header";

    /** Session id carried in the Authorization: Bearer header. */
    const USAGE_TOKEN = "token";

    /** Header name used when sessionUsage is header/token. */
    const HEADER_IDENTIFY = "SESSIONID";

    /** Storage implementation class, must implement SessionStorage. */
    protected string $sessionStorageClass = RedisSessionStorage::class;

    /** Redis connection pool name. */
    protected string $redisName = "default";

    /** Redis logical database index. */
    protected int $database = 0;

    /** How the session id is transported (cookie/header/token). */
    protected string $sessionUsage = self::USAGE_COOKIE;

    /** Cookie domain. */
    protected string $domain = "";

    /** Cookie path. */
    protected string $path = "/";

    /** Session id key (cookie name / header name). */
    protected string $sessionName = "SESSIONID";

    /** Session lifetime in seconds. */
    protected int $timeout = 30 * 60;

    /** Only send the cookie over HTTP(S), block JS access. */
    protected bool $httpOnly = true;

    /** Only send the cookie over HTTPS. */
    protected bool $secure = false;

    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    public function getSessionStorageClass(): string
    {
        return $this->sessionStorageClass;
    }

    public function setSessionStorageClass(string $sessionStorageClass): void
    {
        $this->sessionStorageClass = $sessionStorageClass;
    }

    public function getRedisName(): string
    {
        return $this->redisName;
    }

    public function setRedisName(string $redisName): void
    {
        $this->redisName = $redisName;
    }

    public function getDatabase(): int
    {
        return $this->database;
    }

    public function setDatabase(int $database): void
    {
        $this->database = $database;
    }

    public function getSessionUsage(): string
    {
        return $this->sessionUsage;
    }

    public function setSessionUsage(string $sessionUsage): void
    {
        $this->sessionUsage = $sessionUsage;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getSessionName(): string
    {
        return $this->sessionName;
    }

    public function setSessionName(string $sessionName): void
    {
        $this->sessionName = $sessionName;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function setHttpOnly(bool $httpOnly): void
    {
        $this->httpOnly = $httpOnly;
    }

    public function getSecure(): bool
    {
        return $this->secure;
    }

    public function setSecure(bool $secure): void
    {
        $this->secure = $secure;
    }
}
