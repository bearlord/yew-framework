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

    /**
     * @var SessionConfig|null
     */
    private ?SessionConfig $sessionConfig;

    /**
     * @var SessionStorage
     */
    protected SessionStorage $sessionStorage;

    /**
     * SessionPlugin constructor.
     * @param SessionConfig|null $sessionConfig
     */
    public function __construct(?SessionConfig $sessionConfig = null)
    {
        parent::__construct();
        $this->atAfter(RedisPlugin::class);
        if ($sessionConfig == null) {
            $sessionConfig = new SessionConfig();
        }
        $this->sessionConfig = $sessionConfig;
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new RedisPlugin());
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Session";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        $this->sessionConfig->merge();
        $class = $this->sessionConfig->getSessionStorageClass();
        $this->sessionStorage = new $class($this->sessionConfig);
        $this->setToDIContainer(SessionStorage::class, $this->sessionStorage);
        $this->setToDIContainer(HttpSession::class, new HttpSessionProxy());
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }

    /**
     * @return SessionStorage
     */
    public function getSessionStorage(): SessionStorage
    {
        return $this->sessionStorage;
    }
}