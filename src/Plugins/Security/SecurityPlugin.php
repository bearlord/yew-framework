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
    private $securityConfig;

    public function getName(): string
    {
        return "Security";
    }

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

    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AopPlugin());
        $pluginInterfaceManager->addPlugin(new SessionPlugin());
    }

    public function init(Context $context)
    {
        parent::init($context);
        $this->securityConfig->merge();
        $aopConfig = DIget(AopConfig::class);
        $aopConfig->addAspect(new SecurityAspect());
    }

    public function beforeServerStart(Context $context)
    {
        $this->securityConfig->merge();
    }

    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}
