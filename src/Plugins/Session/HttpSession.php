<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;

use Yew\Core\Server\Beans\Http\Cookie;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Coroutine\Server\Server;

class HttpSession
{
    protected ?bool $isNew = null;

    protected array $attribute = [];

    protected ?string $id = null;

    protected ?SessionStorage $sessionStorage = null;

    protected ?Request $request = null;

    protected ?Response $response = null;

    protected ?SessionConfig $config = null;

    public function __construct()
    {
        $plugin = Server::$instance->getPlugManager()->getPlug(SessionPlugin::class);
        if ($plugin instanceof SessionPlugin) {
            $this->sessionStorage = $plugin->getSessionStorage();
        }
        $this->config = DIGet(SessionConfig::class);
        setContextValue("HttpSession", $this);
        $this->request = getDeepContextValueByClassName(Request::class);
        $this->response = getDeepContextValueByClassName(Response::class);
        $usage = $this->config->getSessionUsage();
        if ($usage === SessionConfig::USAGE_COOKIE) {
            $this->id = $this->request->getCookieParams()[$this->config->getSessionName()] ?? null;
        } elseif ($usage === SessionConfig::USEAGE_HEADER) {
            $identify = $this->request->getHeader(SessionConfig::HEADER_IDENTIFY);
            $this->id = !empty($identify[0]) ? $identify[0] : null;
        } else {
            $authorization = explode(" ", $this->request->getHeaderLine("authorization"));
            if (isset($authorization[1])) {
                $this->id = $authorization[1];
            }
        }
        if ($this->id !== null) {
            $this->isNew = false;
            $result = $this->sessionStorage->get($this->id);
            $this->attribute = $result !== null ? serverUnSerialize($result) : [];
        }
        \Swoole\Coroutine::defer(function () {
            $this->save();
        });
    }

    public function create(): void
    {
        $this->refresh();
    }

    public function isAvailable(): bool
    {
        return $this->isExist() && !$this->isOverdue();
    }

    public function isOverdue(): bool
    {
        return empty($this->attribute);
    }

    public function isExist(): bool
    {
        return $this->id !== null;
    }

    public function isNew(): ?bool
    {
        return $this->isNew;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attribute[$key] = $value;
    }

    public function removeAttribute(string $key): void
    {
        unset($this->attribute[$key]);
    }

    public function refresh(): void
    {
        $id = $this->getId();
        if ($id !== null) {
            $this->sessionStorage->remove($id);
        }
        $this->id = $this->gid();
        $usage = $this->config->getSessionUsage();
        if ($usage === SessionConfig::USAGE_COOKIE) {
            $this->response->withCookie(new Cookie($this->config->getSessionName(), $this->id,
                time() + $this->config->getTimeout(), $this->config->getPath(),
                $this->config->getDomain(), $this->config->getSecure(), $this->config->getHttpOnly()));
        } elseif ($usage === SessionConfig::USEAGE_HEADER) {
            $identify = $this->request->getHeader(SessionConfig::HEADER_IDENTIFY);
            if (!empty($identify[0])) {
                $this->response->withHeader(SessionConfig::HEADER_IDENTIFY, $identify[0]);
            }
        } else {
            $this->response->withHeader("Authorization", "Bearer " . $this->id);
        }
        $this->setAttribute("createTime", time());
        $this->setAttribute("expireTime", time() + $this->config->getTimeout());

        $this->isNew = true;
    }

    public function getExpireTime(): int
    {
        return (int)$this->getAttribute("expireTime");
    }

    public function getAttribute(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->attribute;
        }
        return $this->attribute[$key] ?? $default;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function invalidate(): void
    {
        if ($this->id !== null) {
            $this->sessionStorage->remove($this->id);
            $this->response->withCookie(new Cookie($this->config->getSessionName(), null, time() - 1));
        }
        $this->id = null;
        $this->attribute = [];
    }

    public function destroy(): void
    {
        $this->invalidate();
    }

    private function save(): void
    {
        if (!empty($this->attribute) && $this->id !== null) {
            $this->sessionStorage->set($this->id, serverSerialize($this->attribute));
        }
    }

    private function gid(): string
    {
        return session_create_id();
    }

    public string $flashParam = "__flash";

    public function getFlash(string $key, $defaultValue = null, bool $delete = false)
    {
        $counters = $this->getAttribute($this->flashParam, []);
        if (isset($counters[$key])) {
            $value = $this->getAttribute($key, $defaultValue);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                $counters[$key] = 1;
                $this->setAttribute($this->flashParam, $counters);
            }
            return $value;
        }
        return $defaultValue;
    }

    public function getAllFlashes(bool $delete = false): array
    {
        $counters = $this->getAttribute($this->flashParam, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $this->attribute)) {
                $flashes[$key] = $this->getAttribute($key);
                if ($delete) {
                    unset($counters[$key]);
                    $this->removeAttribute($key);
                } elseif ($counters[$key] < 0) {
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }
        $this->setAttribute($this->flashParam, $counters);
        return $flashes;
    }

    public function setFlash(string $key, $value = true, bool $removeAfterAccess = true): void
    {
        $counters = $this->getAttribute($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->setAttribute($key, $value);
        $this->setAttribute($this->flashParam, $counters);
    }

    public function addFlash(string $key, $value = true, bool $removeAfterAccess = true): void
    {
        $counters = $this->getAttribute($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->setAttribute($this->flashParam, $counters);

        $attribute = $this->getAttribute($key);
        if (empty($attribute)) {
            $this->setAttribute($key, [$value]);
        } elseif (is_array($attribute)) {
            $attribute[] = $value;
            $this->setAttribute($key, $attribute);
        } else {
            $this->setAttribute($key, [$attribute, $value]);
        }
    }

    public function removeFlash(string $key)
    {
        $counters = $this->getAttribute($this->flashParam, []);
        $attribute = $this->getAttribute($key);
        $value = $attribute ?? null;
        unset($counters[$key]);
        $this->removeAttribute($key);
        $this->setAttribute($this->flashParam, $counters);
        return $value;
    }

    public function removeAllFlashes(): void
    {
        $counters = $this->getAttribute($this->flashParam, []);
        foreach (array_keys($counters) as $key) {
            $this->removeAttribute($key);
        }
        $this->removeAttribute($this->flashParam);
    }

    public function hasFlash(string $key): bool
    {
        return $this->getFlash($key) !== null;
    }
}
