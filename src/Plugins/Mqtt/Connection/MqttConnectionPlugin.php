<?php
/**
 * Yew framework - Connection plugin
 *
 * Registers a dedicated helper process that stores connection-level routing
 * state (fd <-> uid, clientId <-> uid, clientId <-> session_start) so the data
 * survives worker restarts, unlike the static properties on Server.
 */

namespace Yew\Plugins\Mqtt\Connection;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Mqtt\Connection\MqttConnection;
use Yew\Plugins\Mqtt\Connection\MqttConnectionConfig;
use Yew\Plugins\Mqtt\Connection\MqttConnectionProcess;

class MqttConnectionPlugin extends AbstractPlugin
{
    const PROCESS_GROUP_NAME = "HelperGroup";

    /**
     * @var MqttConnectionConfig
     */
    private MqttConnectionConfig $mqttConnectionConfig;

    public function __construct()
    {
        parent::__construct();

        $this->initConfig();
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
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Connection";
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        $this->mqttConnectionConfig->merge();

        Server::$instance->addProcess(
            $this->mqttConnectionConfig->getProcessName(),
            MqttConnectionProcess::class,
            self::PROCESS_GROUP_NAME
        );
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName()
            == $this->mqttConnectionConfig->getProcessName()
        ) {
            $mqttConnection = new MqttConnection();
            $this->setToDIContainer(MqttConnection::class, $mqttConnection);
        }

        $this->ready();
    }

    /**
     * Init mqttConnectionConfig
     * @return void
     */
    protected function initConfig()
    {
        $this->mqttConnectionConfig = new MqttConnectionConfig();
    }
}
