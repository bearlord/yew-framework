<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Uid;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Aop\AopPlugin;
use Yew\Plugins\Uid\Aspect\UidAspect;

class UidPlugin extends AbstractPlugin
{
    /**
     * @var UidAspect
     */
    private UidAspect $uidAspect;

    /**
     * @var UidConfig|null
     */
    private ?UidConfig $uidConfig;

    /**
     * @var UidBean
     */
    private UidBean $uid;

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "UidBean";
    }

    /**
     * @param UidConfig|null $uidConfig
     */
    public function __construct(?UidConfig $uidConfig = null)
    {
        parent::__construct();
        //Need aop support, so load after aop
        $this->atAfter(AopPlugin::class);
        if ($uidConfig == null) {
            $uidConfig = new UidConfig();
        }
        $this->uidConfig = $uidConfig;
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AopPlugin());
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Yew\Core\Exception\Exception
     */
    public function init(Context $context)
    {
        parent::init($context);
        $serverConfig = Server::$instance->getServerConfig();
        $aopConfig = DIGet(AopConfig::class);
        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/yew-framework/src/");

        $this->uidAspect = new UidAspect();
        $aopConfig->addAspect($this->uidAspect);
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function beforeServerStart(Context $context)
    {
        $this->uidConfig->merge();
        $serverConfig = Server::$instance->getServerConfig();
        $this->uid = new UidBean($serverConfig->getMaxCoroutine(), $this->uidConfig);

        $this->setToDIContainer(UidBean::class, $this->uid);
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }

    /**
     * @return UidAspect
     */
    public function getUidAspect(): UidAspect
    {
        return $this->uidAspect;
    }

}