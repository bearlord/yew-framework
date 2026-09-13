<?php
/**
 * Yew framework - Connection plugin
 *
 * Creates the shared-memory Swoole\Table (in the master process, before the
 * server forks workers) that holds connection-level routing state
 * (fd <-> uid, clientId <-> uid, clientId <-> session_start). Every process
 * reads/writes it locally with no IPC to a helper process, and the data
 * survives worker restarts because it lives in shared memory.
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

        // Create the shared-memory tables ONCE, before the server forks workers,
        // so every process shares the same backing memory (Swoole\Table must be
        // created in the master process to be inherited by forked workers).
        Connection::initTables(
            $this->connectionConfig->getFdTableSize(),
            $this->connectionConfig->getClientTableSize(),
            $this->connectionConfig->getDataColumnSize()
        );
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        // Connection is now a thin wrapper over the shared-memory Swoole\Table,
        // so every process (including workers) needs its own instance for
        // GetConnection to read/write locally without any IPC.
        $connection = new Connection();
        $this->setToDIContainer(Connection::class, $connection);

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
