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
use Yew\Plugins\Whoops\Aspect\WhoopsAspect;
use Yew\Plugins\Whoops\Handler\WhoopsHandler;
use Whoops\Run;

class WhoopsPlugin extends AbstractPlugin
{
    use GetLogger;

    /**
     * @var Run
     */
    private $whoops;

    /**
     * @var WhoopsConfig
     */
    protected $whoopsConfig;

    /**
     * WhoopsPlugin constructor.
     * @param WhoopsConfig|null $whoopsConfig
     * @throws \DI\DependencyException
     * @throws \ReflectionException
     * @throws \DI\NotFoundException
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
        $this->atBefore("Yew\Plugins\EasyRoute\EasyRoutePlugin");
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Whoops";
    }

    /**
     * @inheritDoc
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \Yew\Core\Exception
     * @throws \ReflectionException
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
     * @inheritDoc
     * @param Context $context
     * @return mixed|void
     * @throws \Yew\Core\Exception
     * @throws \Yew\Core\Plugins\Config\ConfigException
     * @throws \ReflectionException
     */
    public function init(Context $context)
    {
        parent::init($context);
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
        
        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/bearlord/yew-framework/src/ESD/");
        $aopConfig->addAspect(new WhoopsAspect($this->whoops, $this->whoopsConfig));
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return mixed
     * @throws \Yew\Core\Plugins\Config\ConfigException
     * @throws \ReflectionException
     */
    public function beforeServerStart(Context $context)
    {
        $this->whoopsConfig->merge();
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