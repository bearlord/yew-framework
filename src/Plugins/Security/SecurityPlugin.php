<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Aop\AopPlugin;
use Yew\Plugins\Security\Aspect\SecurityAspect;
use Yew\Plugins\Session\SessionPlugin;

class SecurityPlugin extends AbstractPlugin
{
    /**
     * @var SecurityConfig|null
     */
    private $securityConfig;

    /**
     * 获取插件名字
     * @return string
     */
    public function getName(): string
    {
        return "Security";
    }

    /**
     * CachePlugin constructor.
     * @param SecurityConfig|null $securityConfig
     * @throws \DI\DependencyException
     * @throws \ReflectionException
     * @throws \DI\NotFoundException
     */
    public function __construct(?SecurityConfig $securityConfig = null)
    {
        parent::__construct();
        $this->atAfter(AopPlugin::class);
        $this->atAfter(SessionPlugin::class);
        if ($securityConfig == null) {
            $securityConfig = new SecurityConfig();
        }
        $this->securityConfig = $securityConfig;
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AopPlugin());
        $pluginInterfaceManager->addPlugin(new SessionPlugin());
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function init(Context $context)
    {
        parent::init($context);
        $this->securityConfig->merge();
        $aopConfig = DIget(AopConfig::class);
        $aopConfig->addAspect(new SecurityAspect());
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function beforeServerStart(Context $context)
    {
        $this->securityConfig->merge();
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return mixed
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}