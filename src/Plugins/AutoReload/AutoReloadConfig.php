<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AutoReload;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Coroutine\Server\Server;

/**
 * Hot-reload configuration (bound to the "reload" key in app config).
 */
class AutoReloadConfig extends BaseConfig
{
    const KEY = "reload";

    protected bool $enable = true;

    protected ?string $monitorDir = null;

    public function __construct()
    {
        parent::__construct(self::KEY);

        $enable = Server::$instance->getConfigContext()->get("yew.reload.enable", true);
        $this->setEnable($enable);
    }

    public function isEnable(): bool
    {
        return $this->enable;
    }

    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    public function getMonitorDir(): ?string
    {
        return $this->monitorDir;
    }

    public function setMonitorDir(?string $monitorDir = null): void
    {
        $this->monitorDir = $monitorDir;
    }
}
