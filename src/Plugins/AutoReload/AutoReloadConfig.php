<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */


namespace Yew\Plugins\AutoReload;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Coroutine\Server\Server;

class AutoReloadConfig extends BaseConfig
{
    const KEY = "reload";

    /**
     * @var bool
     */
    protected bool $enable = true;

    /**
     * Monitor directory
     * @var string|null
     */
    protected ?string $monitorDir = null;
    
    public function __construct()
    {
        parent::__construct(self::KEY);
        
        $enable = Server::$instance->getConfigContext()->get("yew.reload.enable", true);
        $this->setEnable($enable);
    }

    /**
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enable;
    }

    /**
     * @param bool $enable
     */
    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    /**
     * @return string|null
     */
    public function getMonitorDir(): ?string
    {
        return $this->monitorDir;
    }

    /**
     * @param string|null $monitorDir
     */
    public function setMonitorDir(?string $monitorDir = null): void
    {
        $this->monitorDir = $monitorDir;
    }
}