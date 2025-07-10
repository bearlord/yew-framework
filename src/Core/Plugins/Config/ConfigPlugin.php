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
     * @var string
     */
    protected string $configDir;

    /**
     * @var ConfigContext
     */
    protected $configContext;
    

    public function __construct()
    {
        parent::__construct();

        if (defined("RES_DIR")) {
            $configDir = RES_DIR;
        } else {
            $configDir = Server::$instance->getServerConfig()->getRootDir() . "/resources";
        }
        $this->setConfigDir($configDir);

        $this->configContext = DIGet(ConfigContext::class);
        $this->atAfter(EventPlugin::class);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Config";
    }

    /**
     * @param Context $context
     */
    public function beforeServerStart(Context $context)
    {
        $baseFile = Server::$instance->getServerConfig()->getFrameworkDir() . '/Framework/Config/resources/base.yml';
        if (is_file($baseFile)) {
            $this->configContext->addDeepConfig(Yaml::parseFile($baseFile), self::ConfigDeep);
        }

        $bootstrapFile = $this->getConfigDir() . "/bootstrap.yml";
        if (is_file($bootstrapFile)) {
            $this->configContext->addDeepConfig(Yaml::parseFile($bootstrapFile), self::BootstrapDeep);
        }

        $applicationFile = $this->getConfigDir() . "/application.yml";
        if (is_file($applicationFile)) {
            $this->configContext->addDeepConfig(Yaml::parseFile($applicationFile), self::ApplicationDeep);
        }

        $active = $this->configContext->get("yew.profiles.active");
        if (!empty($active)) {
            $applicationActiveFile = $this->getConfigDir() . "/application-$active.yml";
            if (is_file($applicationActiveFile)) {
                $this->configContext->addDeepConfig(Yaml::parseFile($applicationActiveFile), self::ApplicationActiveDeep);
            }
        }
    }

    /**
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

    /**
     * @return string
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    /**
     * @param string $configDir
     * @return void
     */
    public function setConfigDir(string $configDir): void
    {
        $this->configDir = $configDir;
    }


}