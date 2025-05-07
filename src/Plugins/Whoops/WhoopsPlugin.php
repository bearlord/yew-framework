<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Whoops;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Aop\AopPlugin;
use Yew\Plugins\Route\RoutePlugin;
use Yew\Plugins\Whoops\Aspect\WhoopsAspect;
use Yew\Plugins\Whoops\Handler\WhoopsHandler;
use Whoops\Run;

class WhoopsPlugin extends AbstractPlugin
{
    use GetLogger;

    /**
     * @var Run
     */
    private Run $whoops;

    /**
     * @var WhoopsConfig|null
     */
    protected ?WhoopsConfig $whoopsConfig;

    /**
     * @param WhoopsConfig|null $whoopsConfig
     */
    public function __construct(?WhoopsConfig $whoopsConfig = null)
    {
        parent::__construct();
        if ($whoopsConfig == null) {
            $whoopsConfig = new WhoopsConfig();
        }
        $this->whoopsConfig = $whoopsConfig;

        //Need aop support, so load after aop
        $this->atAfter(AopPlugin::class);

        //Due to Aspect sorting issues need to be loaded before EasyRoutePlugin
        $this->atBefore(RoutePlugin::class);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Whoops";
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $aopPlugin = $pluginInterfaceManager->getPlug(AopPlugin::class);
        if ($aopPlugin == null) {
            $pluginInterfaceManager->addPlugin(new AopPlugin());
        }
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     * @throws \Yew\Core\Exception\Exception
     * @throws \Exception
     */
    public function init(Context $context)
    {
        parent::init($context);

        /** @var AopConfig $aopConfig */
        $aopConfig = DIGet(AopConfig::class);

        $serverConfig = Server::$instance->getServerConfig();

        $this->whoopsConfig->merge();
        $this->whoops = new Run();
        $this->whoops->writeToOutput(false);
        $this->whoops->allowQuit(false);

        $handler = new WhoopsHandler();
        $handler->addResourcePath($serverConfig->getVendorDir() . "/filp/whoops/src/Whoops/Resources/");
        $handler->setPageTitle("Whoops! There was an error.");
        $this->whoops->pushHandler($handler);
        
        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/bearlord/yew-framework/src/");
        $aopConfig->addAspect(new WhoopsAspect($this->whoops, $this->whoopsConfig));
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function beforeServerStart(Context $context)
    {
        $this->whoopsConfig->merge();
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}