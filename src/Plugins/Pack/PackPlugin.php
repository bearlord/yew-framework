<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack;

use Yew\Core\Exception\Exception;
use Yew\Core\Server\Config\PortConfig;
use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Aop\AopPlugin;
use Yew\Plugins\Pack\Aspect\PackAspect;

/**
 * Class PackPlugin
 * @package Yew\Plugins\Pack
 */
class PackPlugin extends AbstractPlugin
{
    /**
     * @var PackConfig[]
     */
    private array $packConfigs = [];

    /**
     * @var PackAspect
     */
    private PackAspect $packAspect;

    /**
     * EasyRoutePlugin constructor.
     */
    public function __construct()
    {
        parent::__construct();
        //Need aop support, so load after aop
        $this->atAfter(AopPlugin::class);
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Pack";
    }

    /**
     * @param Context $context
     * @return mixed|void
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \Yew\Core\Plugins\Config\ConfigException
     * @throws \Yew\Core\Exception
     * @throws \ReflectionException
     */
    public function init(Context $context)
    {
        parent::init($context);

        $configs = Server::$instance->getConfigContext()->get(PortConfig::KEY);
        foreach ($configs as $key => $value) {
            $packConfig = new PackConfig();
            $packConfig->setName($key);
            $packConfig->buildFromConfig($value);
            //Handling packtool
            if($packConfig->getPackTool()!=null){
                $class = $packConfig->getPackTool();
                if(class_exists($class)){
                    $class::changePortConfig($packConfig);
                }else{
                    throw new Exception("$class pack class was not found");
                    exit(-1);
                }
            }
            $packConfig->merge();
            $this->packConfigs[$packConfig->getPort()] = $packConfig;
        }

        $serverConfig = Server::$instance->getServerConfig();
        /** @var AopConfig $aopConfig */
        $aopConfig = DIget(AopConfig::class);
        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/yew/src/yew/");
        $this->packAspect = new PackAspect($this->packConfigs);
        $aopConfig->addAspect($this->packAspect);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     * @throws \Yew\Core\Exception
     * @throws \ReflectionException
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AopPlugin());
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return mixed
     */
    public function beforeServerStart(Context $context)
    {
        return;
    }

    /**
     * @inheritDoc
     * @param Context $context
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }

    /**
     * @return PackAspect
     */
    public function getPackAspect(): PackAspect
    {
        return $this->packAspect;
    }
}