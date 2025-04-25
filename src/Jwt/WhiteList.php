<?php

namespace Yew\Jwt;

use Yew\Plugins\Redis\GetRedis;

class WhiteList
{
    use GetRedis;

    /**
     * @var string
     */
    protected string $loginType;

    /**
     * @var string
     */
    protected string $ssoKey;

    /**
     * @var string
     */
    protected string $cachePrefix;

    /**
     * @return string
     */
    public function getLoginType(): string
    {
        return $this->loginType;
    }

    /**
     * @param string $loginType
     * @return WhiteList
     */
    public function setLoginType(string $loginType): WhiteList
    {
        $this->loginType = $loginType;
        return $this;
    }

    /**
     * @return string
     */
    public function getSsoKey(): string
    {
        return $this->ssoKey;
    }

    /**
     * @param string $ssoKey
     * @return WhiteList
     */
    public function setSsoKey(string $ssoKey): WhiteList
    {
        $this->ssoKey = $ssoKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getCachePrefix(): string
    {
        return $this->cachePrefix;
    }

    /**
     * @param string $cachePrefix
     * @return WhiteList
     */
    public function setCachePrefix(string $cachePrefix): WhiteList
    {
        $this->cachePrefix = $cachePrefix;
        return $this;
    }


    /**
     * @param array $payload
     * @return bool
     * @throws \Throwable
     */
    public function effective(array $payload): bool
    {
        switch (true) {
            case ($this->loginType == 'mpop'):
                return true;

            case ($this->loginType == 'sso'):
                $val = $this->redis()->get($this->cachePrefix . $payload['scope'] . ":" . $payload['aud']);
                return $payload['jti'] == $val;

            default:
                return false;
        }
    }

    /**
     * @param string $uid
     * @param string $version
     * @param string|null $type
     * @return bool
     * @throws \Throwable
     */
    public function add(string $uid, string $version, ?string $type = Jwt::SCOPE_TOKEN): bool
    {
        return $this->redis()->set($this->cachePrefix . $type . ":" . $uid, $version);
    }

    /**
     * @param string $uid
     * @param string|null $type
     * @return bool
     * @throws \Throwable
     */
    public function remove(string $uid, ?string $type = Jwt::SCOPE_TOKEN): bool
    {
        return $this->redis()->set($this->cachePrefix . $type . ":" . $uid, 0, 7200);
    }
}
