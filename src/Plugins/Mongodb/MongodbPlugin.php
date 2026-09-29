<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Mongodb;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Plugins\Yew\YewPlugin;
use Yew\Core\Server\Server;
use Yew\Yew;

class MongodbPlugin extends AbstractPlugin
{
    use GetLogger;

    /**
     * Dedicated process name for the RemoteObject server.
     */
    public const PROCESS_NAME = 'mongodb-remote-object';

    /**
     * @var Configs
     */
    protected Configs $configs;

    /**
     * @var RemoteObjectConfig
     */
    protected RemoteObjectConfig $remoteObjectConfig;

    /**
     * MongodbPlugin constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->atAfter(YewPlugin::class);
        $this->configs = new Configs();
        $this->remoteObjectConfig = new RemoteObjectConfig();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Mongodb";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        $this->remoteObjectConfig->buildFromConfig(
            Server::$instance->getConfigContext()->get("yew.mongodbRemoteObject", [])
        );

        $configs = Server::$instance->getConfigContext()->get("yew.mongodb", []);
        if (empty($configs)) {
            return;
        }

        foreach ($configs as $key => $config) {
            $configObject = new Config((string)$key);
            $configObject->buildFromConfig($config);

            // RemoteObject server is shared globally; propagate its params to each connection config.
            $configObject->setRemoteObjectEnable($this->remoteObjectConfig->isEnable());
            $configObject->setRemoteObjectHost($this->remoteObjectConfig->getHost());
            $configObject->setRemoteObjectPort($this->remoteObjectConfig->getPort());
            $configObject->setRemoteObjectClass($this->remoteObjectConfig->getRemoteClass());
            $configObject->setRemoteObjectApiKey($this->remoteObjectConfig->getApiKey());

            $this->configs->addConfig($configObject);
        }

        $this->setToDIContainer(RemoteObjectConfig::class, $this->remoteObjectConfig);

        if ($this->remoteObjectConfig->isEnable()) {
            Server::$instance->addProcess(
                self::PROCESS_NAME,
                RemoteObjectServerProcess::class,
                self::PROCESS_NAME
            );
        }
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        $configs = $this->configs->getConfigs();
        if (empty($configs)) {
            $this->warn("Mongodb configuration not found");
            $this->ready();

            return;
        }

        if ($this->isRemoteObjectProcess()) {
            $this->startRemoteObjectServer();

            return;
        }

        $pools = new MongodbPools();

        /**
         * @var string $key
         * @var Config $config
         */
        foreach ($configs as $key => $config) {
            $pool = new MongodbPool($config);
            $pools->addPool($pool);
        }

        $context->add("MongodbPools", $pools);

        $this->setToDIContainer(MongodbPools::class, $pools);
        $this->setToDIContainer(MongodbPool::class, $pools->getPool());

        if ($this->remoteObjectConfig->isEnable()) {
            $this->info(sprintf(
                "Mongodb RemoteObject enabled: %s:%d (%s)",
                $this->remoteObjectConfig->getHost(),
                $this->remoteObjectConfig->getPort(),
                $this->remoteObjectConfig->getRemoteClass()
            ));
        }

        $this->ready();
    }

    /**
     * @throws \Throwable
     */
    protected function startRemoteObjectServer(): void
    {
        $cfg = $this->remoteObjectConfig;

        Yew::$app = new RemoteObjectApplication();

        $serverMode = $cfg->getServerMode();
        if ($serverMode <= 0) {
            $serverMode = defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 2;
        }

        $options = [
            'worker_num'  => $cfg->getWorkerNum() > 0 ? $cfg->getWorkerNum() : 16,
            'server_mode' => $serverMode
        ];

        $allowedClasses = $cfg->getAllowedClasses() ?: $cfg->getDefaultAllowedClasses();
        if (!empty($allowedClasses)) {
            $options['allowed_classes'] = $allowedClasses;
        }

        if ($cfg->getApiKey() !== "") {
            $options['api_key'] = $cfg->getApiKey();
        }

        $this->info(sprintf(
            "[%s] starting at %s:%d (server_mode=%d, worker_num=%d, class=%s)",
            self::PROCESS_NAME,
            $cfg->getHost(),
            $cfg->getPort(),
            $serverMode,
            $options['worker_num'],
            $cfg->getRemoteClass()
        ));

        $this->ready();

        $server = new \Swoole\RemoteObject\Server(
            host: $cfg->getHost(),
            port: $cfg->getPort(),
            options: $options
        );

        // Blocks: this process becomes the RemoteObject server from here on.
        $server->start();
    }

    /**
     * @return bool
     */
    protected function isRemoteObjectProcess(): bool
    {
        $process = Server::$instance->getProcessManager()->getCurrentProcess();

        return $process !== null && $process->getProcessName() === self::PROCESS_NAME;
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
    }
}
