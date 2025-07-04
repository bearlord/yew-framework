<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Database;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Server;
use Yew\Core\Context\Context;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Yew\YewPlugin;


class DatabasePlugin extends \Yew\Core\Plugin\AbstractPlugin
{
    use GetLogger;

    protected Configs $configs;

    /**
     * DatabasePlugin constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->atAfter(YewPlugin::class);
        $this->configs = new Configs();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Database';
    }

    /**
     * @param Context $context
     * @return void
     */
    public function init(Context $context)
    {
        parent::init($context);
    }

    /**
     *
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        $configs = Server::$instance->getConfigContext()->get("db");

        if (empty($configs)) {
            return;
        }

        foreach ($configs as $key => $config) {
            $configObject = new Config($key);
            $this->configs->addConfig($configObject->buildFromConfig($config));

            $slaveConfigs = $this->getSlaveConfigs($config);
            if (!empty($slaveConfigs)) {
                foreach ($slaveConfigs as $slaveKey => $slaveConfig) {
                    $_salveKey = sprintf("%s.slave.%s", $key, $slaveKey);
                    $slaveConfigObject = new Config($_salveKey);
                    $this->configs->addConfig($slaveConfigObject->buildFromConfig($slaveConfig));
                }
            }

            $masterConfigs = $this->getMasterConfigs($config);
            if (!empty($masterConfigs)) {
                foreach ($masterConfigs as $masterKey => $masterConfig) {
                    $_masterKey = sprintf("%s.master.%s", $key, $masterKey);
                    $masterConfigObject = new Config($_masterKey);
                    $this->configs->addConfig($masterConfigObject->buildFromConfig($masterConfigs));
                }
            }
        }
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     */
    public function beforeProcessStart(Context $context)
    {
        $pools = new DatabasePools();

        $configs = $this->configs->getConfigs();
        if (empty($configs)) {
            $this->warn('Database configuration not found');
            return;
        }

        foreach ($configs as $config) {
            $pool = new DatabasePool($config);
            $pools->addPool($pool);
            $this->debug(sprintf("%s connection pool named %s created", ucfirst($config->getDriverName()), $config->getName()));
        }

        $context->add("DatabasePools", $pools);

        $this->setToDIContainer(DatabasePools::class, $pools);
        $this->setToDIContainer(DatabasePool::class, $pools->getPool());

        $this->ready();
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
    }

    /**
     * @param array $config
     * @return array|bool
     */
    protected function getMasterConfigs(array $config)
    {
        if (empty($config['masters'])) {
            return false;
        }
        if (empty($config['masterConfig'])) {
            return false;
        }
        $row = [];
        foreach ($config['masters'] as $v) {
            $v['username'] = $config['masterConfig']['username'];
            $v['password'] = $config['masterConfig']['password'];
            $v['poolMaxNumber'] = $config['masterConfig']['poolMaxNumber'];
            $v['charset'] = $config['charset'];
            $v['tablePrefix'] = $config['tablePrefix'];
            $v['enableSchemaCache'] = $config['enableSchemaCache'];
            $v['schemaCacheDuration'] = $config['schemaCacheDuration'];
            $v['schemaCache'] = $config['schemaCache'];
            $row[] = $v;
        }
        return $row;
    }

    /**
     * @param array $config
     * @return array|bool
     */
    protected function getSlaveConfigs(array $config)
    {
        if (empty($config['slaves'])) {
            return false;
        }
        if (empty($config['slaveConfig'])) {
            return false;
        }
        $row = [];
        foreach ($config['slaves'] as $v) {
            $v['username'] = $config['slaveConfig']['username'];
            $v['password'] = $config['slaveConfig']['password'];
            $v['poolMaxNumber'] = $config['slaveConfig']['poolMaxNumber'];
            $v['charset'] = $config['charset'];
            $v['tablePrefix'] = $config['tablePrefix'];
            $v['enableSchemaCache'] = $config['enableSchemaCache'];
            $v['schemaCacheDuration'] = $config['schemaCacheDuration'];
            $v['schemaCache'] = $config['schemaCache'];
            $row [] = $v;
        }
        return $row;
    }
}
