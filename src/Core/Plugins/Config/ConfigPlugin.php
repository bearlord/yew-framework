<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Config;

use Exception;
use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugins\Event\EventPlugin;
use Yew\Core\Server\Server;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ConfigPlugin
 * @package Yew\Core\Plugins\Config
 */
class ConfigPlugin extends AbstractPlugin
{
    //manually configuration
    const ConfigDeep = 10;

    //bootstrap.yml
    const BootstrapDeep = 9;

    //application.yml
    const ApplicationDeep = 8;

    //application-active.yml
    const ApplicationActiveDeep = 7;

    //Remote Global Application Configuration
    const ConfigServerGlobalApplicationDeep = 6;

    //Remote Application Configuration
    const ConfigServerApplicationDeep = 5;

    //Remote Application/Active Configuration
    const ConfigServerApplicationActiveDeep = 4;

    /**
     * @var ConfigConfig|null
     */
    protected ?ConfigConfig $configConfig;

    /**
     * @var ConfigContext
     */
    protected $configContext;

    /**
     * @param ConfigConfig|null $configConfig
     * @throws Exception
     */
    public function __construct(?ConfigConfig $configConfig = null)
    {
        parent::__construct();
        if ($configConfig == null) {
            if (defined("RES_DIR")) {
                $path = RES_DIR;
            } else {
                $path = Server::$instance->getServerConfig()->getRootDir() . "/resources";
            }
            $configConfig = new ConfigConfig($path);
        }
        $this->configConfig = $configConfig;
        $this->configContext = DIGet(ConfigContext::class);
        $this->atAfter(EventPlugin::class);
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Config";
    }

    /**
     * @inheritDoc
     * @param Context $context
     */
    public function beforeServerStart(Context $context)
    {
        $bootstrapFile = $this->configConfig->getConfigDir() . "/bootstrap.yml";
        if (is_file($bootstrapFile)) {
            $this->configContext->addDeepConfig(Yaml::parseFile($bootstrapFile), self::BootstrapDeep);
        }

        $applicationFile = $this->configConfig->getConfigDir() . "/application.yml";
        if (is_file($applicationFile)) {
            $this->configContext->addDeepConfig(Yaml::parseFile($applicationFile), self::ApplicationDeep);
        }

        $active = $this->configContext->get("yew.profiles.active");
        if (!empty($active)) {
            $applicationActiveFile = $this->configConfig->getConfigDir() . "/application-$active.yml";
            if (is_file($applicationActiveFile)) {
                $this->configContext->addDeepConfig(Yaml::parseFile($applicationActiveFile), self::ApplicationActiveDeep);
            }
        }
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
     * @return ConfigContext
     */
    public function getConfigContext(): ConfigContext
    {
        return $this->configContext;
    }

}