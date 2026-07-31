<?php
/**
 * Yew framework - Connection plugin
 *
 * Registers a dedicated helper process that stores connection-level routing
 * state (fd <-> uid, clientId <-> uid, clientId <-> session_start) so the data
 * survives worker restarts, unlike the static properties on Server.
 */

namespace Yew\Plugins\Connection;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;

class ConnectionPlugin extends AbstractPlugin
{
    const PROCESS_GROUP_NAME = "HelperGroup";

    /**
     * @var ConnectionConfig
     */
    private ConnectionConfig $connectionConfig;

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
        $this->connectionConfig->merge();

        Server::$instance->addProcess(
            $this->connectionConfig->getProcessName(),
            ConnectionProcess::class,
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
            == $this->connectionConfig->getProcessName()
        ) {
            $connection = new Connection();
            $this->setToDIContainer(Connection::class, $connection);
        }

        $this->ready();
    }

    /**
     * Init config
     * @return void
     */
    protected function initConfig()
    {
        $this->connectionConfig = new ConnectionConfig();
    }
}
