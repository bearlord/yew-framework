<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Plugins\Redis\RedisPlugin;

class SessionPlugin extends AbstractPlugin
{
    private ?SessionConfig $sessionConfig;

    protected SessionStorage $sessionStorage;

    public function __construct(?SessionConfig $sessionConfig = null)
    {
        parent::__construct();
        $this->atAfter(RedisPlugin::class);
        if ($sessionConfig == null) {
            $sessionConfig = new SessionConfig();
        }
        $this->sessionConfig = $sessionConfig;
    }

    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new RedisPlugin());
    }

    public function getName(): string
    {
        return "Session";
    }

    public function beforeServerStart(Context $context)
    {
        $this->sessionConfig->merge();
        $class = $this->sessionConfig->getSessionStorageClass();
        $this->sessionStorage = new $class($this->sessionConfig);
        $this->setToDIContainer(SessionStorage::class, $this->sessionStorage);
        $this->setToDIContainer(HttpSession::class, new HttpSessionProxy());
    }

    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }

    public function getSessionStorage(): SessionStorage
    {
        return $this->sessionStorage;
    }
}
