<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server;

use Carbon\Carbon;
use Exception;
use Iterator;
use ReflectionException;
use Throwable;
use Yew\Core\Exception\ConfigException;
use Yew\Core\Log\Logger;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Node\BaseNode;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Context\Context;
use Yew\Core\Context\ContextBuilder;
use Yew\Core\Context\ContextManager;
use Yew\Core\DI\DI;
use Yew\Core\Log\Log;
use Yew\Core\Log\LoggerInterface;
use Yew\Core\Plugins\Config\ConfigContext;
use Yew\Core\Plugins\Config\ConfigPlugin;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Plugins\Event\EventPlugin;
use Yew\Core\Plugins\Logger\LoggerPlugin;
use Yew\Core\Server\Beans\ClientInfo;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\RequestProxy;
use Yew\Core\Server\Beans\Response;
use Yew\Core\Server\Beans\ResponseProxy;
use Yew\Core\Server\Beans\ServerStats;
use Yew\Core\Server\Beans\WebSocketFrame;
use Yew\Core\Server\Config\PortConfig;
use Yew\Core\Server\Config\ServerConfig;
use Yew\Core\Server\Port\PortManager;
use Yew\Core\Server\Port\ServerPort;
use Yew\Core\Server\Process\ManagerProcess;
use Yew\Core\Server\Process\MasterProcess;
use Yew\Core\Server\Process\Process;
use Yew\Core\Server\Process\ProcessManager;
use DI\Container;
use Yew\Coroutine\Coroutine;

abstract class Server extends BaseNode
{
    /**
     * @var Server|null
     */
    public static ?Server $instance = null;

    /**
     * Whether to start
     * @var bool
     */
    public static bool $isStart = false;

    /**
     * Server start time
     * @var string|null
     */
    public static ?string $serverStartTime = null;

    /**
     * server configuration
     * @var ServerConfig
     */
    protected ServerConfig $serverConfig;

    /**
     * Swoole server
     * @var \Swoole\Server|null
     */
    protected ?\Swoole\Server $server = null;

    /**
     * Server port
     * @var ServerPort
     */
    private ServerPort $mainPort;

    /**
     * @var ProcessManager
     */
    protected ProcessManager $processManager;

    /**
     * @var PortManager
     */
    protected PortManager $portManager;

    /**
     * @var PluginInterfaceManager
     */
    protected PluginInterfaceManager $plugManager;

    /**
     * @var PluginInterfaceManager
     */
    protected PluginInterfaceManager $basePluginManager;

    /**
     * Is it configured
     * @var bool
     */
    private bool $configured = false;

    /**
     * @var Context|null
     */
    protected ?Context $context = null;

    /**
     * @param ServerConfig $serverConfig
     * @param string $defaultPortClass
     * @param string $defaultProcessClass
     * @throws ReflectionException
     * @throws ConfigException
     * @throws Exception
     */
    public function __construct(ServerConfig $serverConfig, string $defaultPortClass, string $defaultProcessClass)
    {
        parent::__construct();

        self::$instance = $this;

        $this->serverConfig = $serverConfig;

        $this->container->set(Server::class, $this);
        $this->container->set(ServerConfig::class, $this->serverConfig);
        $this->container->set(Response::class, new ResponseProxy());
        $this->container->set(Request::class, new RequestProxy());

        //Set time zone
        $this->setTimeZone($this->serverConfig);

        //Set exception handle
        $this->setExceptionHandler();

        //Register the Process's ContextBuilder
        $contextBuilder = ContextManager::getInstance()->getContextBuilder(ContextBuilder::SERVER_CONTEXT,
            function () {
                return new ServerContextBuilder($this);
            });
        $this->context = $contextBuilder->build();

        //-------------------------------------------------------------------------------------
        $this->setPortManager(new PortManager($this, $defaultPortClass));

        $this->setProcessManager(new ProcessManager($this, $defaultProcessClass));

        $this->setBasePluginManager(new PluginInterfaceManager($this));

        //Initialize the default plugin and add the Config/Logger/Event plugin
        $this->basePluginManagerInit();

        //Merge ServerConfig configuration
        $this->serverConfig->merge();

        //Only get the above to initialize the plugManager
        $this->plugManager = new PluginInterfaceManager($this);
        $this->container->set(PluginInterfaceManager::class, $this->getPlugManager());

        $this->initProcessTable();
    }

    /**
     * @var Table
     */
    protected Table $processTable;

    /**
     * @return Table
     */
    public function getProcessTable(): Table
    {
        return $this->processTable;
    }

    /**
     * @return void
     */
    public function initProcessTable()
    {
        $this->processTable = new Table(1000);
        $this->processTable->column("process_name", Table::TYPE_STRING, 200);

        //0: init, 1: Preparing, 2: Ready
        $this->processTable->column("status", Table::TYPE_INT, 100);

        $this->processTable->column("init_time", Table::TYPE_STRING, 100);

        $this->processTable->column("ready_time", Table::TYPE_STRING, 100);

        $this->processTable->column("last_exit_time", Table::TYPE_STRING, 100);

        $this->processTable->create();
    }

    /**
     * Add a port instance and a class to initialize the instance through configuration
     *
     * @param string $name
     * @param PortConfig $portConfig
     * @param null $portClass
     * @throws ConfigException
     */
    public function addPort(string $name, PortConfig $portConfig, $portClass = null)
    {
        if ($this->isConfigured()) {
            throw new ConfigException("Configuration is locked, please add before calling configure");
        }
        $this->portManager->addPortConfig($name, $portConfig, $portClass);
    }

    /**
     * Add process
     *
     * @param string $name
     * @param null $processClass
     * @param string $groupName
     * @throws ConfigException
     */
    public function addProcess(string $name, $processClass = null, string $groupName = Process::DEFAULT_GROUP)
    {
        if ($this->isConfigured()) {
            throw new ConfigException("Configuration is locked, please add before calling configure");
        }
        $this->processManager->addCustomProcessesConfig($name, $processClass, $groupName);
    }

    /**
     * Adding plugins and adding configuration can only occur before configure
     *
     * @throws ReflectionException|ConfigException
     * @throws Exception
     */
    public function configure()
    {
        //First generate partial configuration
        $this->getPortManager()->mergeConfig();
        $this->getProcessManager()->mergeConfig();

        //Plugin ordering is not allowed at this time
        $this->plugManager->order();
        $this->plugManager->init($this->context);
        $this->pluginInitialized();

        //Call beforeServerStart of all plugins
        $this->plugManager->beforeServerStart($this->context);

        //Lock configuration
        $this->setConfigured(true);

        Coroutine::set([
            'enable_deadlock_check' => false,
        ]);

        //Setting up the main process
        $managerProcess = new ManagerProcess($this);
        $masterProcess = new MasterProcess($this);
        $this->processManager->setMasterProcess($masterProcess);
        $this->processManager->setManagerProcess($managerProcess);

        //Set process name
        Process::setProcessTitle($this->serverConfig->getName());

        //Create a port instance
        $this->getPortManager()->createPorts();

        //Main port
        if ($this->portManager->hasWebSocketPort()) {
            foreach ($this->portManager->getPorts() as $serverPort) {
                if ($serverPort->isWebSocket()) {
                    $this->mainPort = $serverPort;
                    break;
                }
            }

            $this->server = new \Swoole\WebSocket\Server($this->mainPort->getPortConfig()->getHost(),
                $this->mainPort->getPortConfig()->getPort(),
                SWOOLE_PROCESS,
                $this->mainPort->getPortConfig()->getSwooleSockType()
            );
        } else if ($this->portManager->hasHttpPort()) {
            foreach ($this->portManager->getPorts() as $serverPort) {
                if ($serverPort->isHttp()) {
                    $this->mainPort = $serverPort;
                    break;
                }
            }

            $this->server = new \Swoole\Http\Server($this->mainPort->getPortConfig()->getHost(),
                $this->mainPort->getPortConfig()->getPort(),
                SWOOLE_PROCESS,
                $this->mainPort->getPortConfig()->getSwooleSockType()
            );
        } else {
            $this->mainPort = array_values($this->getPortManager()->getPorts())[0];

            $this->server = new \Swoole\Server($this->mainPort->getPortConfig()->getHost(),
                $this->mainPort->getPortConfig()->getPort(),
                SWOOLE_PROCESS,
                $this->mainPort->getPortConfig()->getSwooleSockType()
            );
        }
        $portConfigData = $this->mainPort->getPortConfig()->buildConfig();
        $serverConfigData = $this->serverConfig->buildConfig();
        $serverConfigData = array_merge($portConfigData, $serverConfigData);
        $this->server->set($serverConfigData);

        //Multiple ports
        foreach ($this->portManager->getPorts() as $serverPort) {
            $serverPort->create();
        }

        //Configure callback
        $this->server->on("start", [$this, "_onStart"]);
        $this->server->on("shutdown", [$this, "_onShutdown"]);
        $this->server->on("workerError", [$this, "_onWorkerError"]);
        $this->server->on("workerExit", [$this, "_onWorkerExit"]);
        $this->server->on("managerStart", [$this, "_onManagerStart"]);
        $this->server->on("managerStop", [$this, "_onManagerStop"]);
        $this->server->on("workerStart", [$this, "_onWorkerStart"]);
        $this->server->on("pipeMessage", [$this, "_onPipeMessage"]);
        $this->server->on("workerStop", [$this, "_onWorkerStop"]);

        //Configuration process
        $this->processManager->createProcess();

        //$this->configureReady();
    }

    /**
     * Plugin initialization is complete
     * @return mixed
     */
    abstract public function pluginInitialized();

    /**
     * All configuration plugins have been initialized
     * @return mixed
     */
    abstract public function configureReady();

    /**
     * Internal Swoole "start" callback.
     *
     * Marks the server as started, records the start time, fires the
     * ApplicationStarting event, boots the master process and finally calls
     * the user-defined onStart() hook.
     */
    public function _onStart()
    {
        Server::$isStart = true;
        Server::$serverStartTime = (Carbon::now())->format("Y-m-d H:i:s.u");

        $startTimeFile = ROOT_DIR . '/runtime/start_time';
        file_put_contents($startTimeFile, Server::$serverStartTime);

        //Send Application Starting Event
        $this->getEventDispatcher()->dispatchEvent(new ApplicationEvent(ApplicationEvent::ApplicationStartingEvent, $this));
        $this->processManager->getMasterProcess()->onProcessStart();

        try {
            $this->onStart();
        } catch (Throwable $e) {
            $this->getLog()->error($e);
        }
    }

    /**
     * Internal Swoole "shutdown" callback.
     *
     * Fires the ApplicationShutdown event and calls the user-defined
     * onShutdown() hook.
     */
    public function _onShutdown()
    {
        //Send Application Shutdown Event
        $this->getEventDispatcher()->dispatchEvent(new ApplicationEvent(ApplicationEvent::ApplicationShutdownEvent, $this));
        try {
            $this->onShutdown();
        } catch (Throwable $e) {
            $this->getLog()->error($e);
        }
    }

    /**
     * On worker error
     *
     * @param $serv
     * @param int $workerId
     * @param int $workerPid
     * @param int $exitCode
     * @param int $signal
     * @throws Exception
     */
    public function _onWorkerError($serv, int $workerId, int $workerPid, int $exitCode, int $signal)
    {
        $process = $this->processManager->getProcessFromId($workerId);
        $this->getLog()->alert("workerId:$workerId exitCode:$exitCode signal:$signal");
        try {
            $this->onWorkerError($process, $exitCode, $signal);
        } catch (Throwable $e) {
            $this->getLog()->error($e);
        }
    }

    /**
     * On worker exit
     *
     * @param $serv
     * @param int $workerId
     * @return bool
     */
    public function _onWorkerExit($serv, int $workerId)
    {
        //\Swoole\Timer::clearAll();
        return true;
    }

    /**
     * On manager start
     *
     * @throws Exception
     */
    public function _onManagerStart()
    {
        Server::$isStart = true;
        Server::$serverStartTime = (Carbon::now())->format("Y-m-d H:i:s.u");

        $this->processManager->getManagerProcess()->onProcessStart();
        try {
            $this->onManagerStart();
        } catch (Throwable $e) {
            $this->getLog()->error($e);
        }
    }

    /**
     * On manager stop
     *
     * @throws Exception
     */
    public function _onManagerStop()
    {
        $this->processManager->getManagerProcess()->onProcessStop();
        try {
            $this->onManagerStop();
        } catch (Throwable $e) {
            $this->getLog()->error($e);
        }
    }

    /**
     * Internal Swoole "workerStart" callback.
     *
     * Marks the server as started, records the start time and delegates to the
     * matching process object so it can run its own start logic.
     *
     * @param \Swoole\Server $server
     * @param int            $workerId
     */
    public function _onWorkerStart($server, int $workerId)
    {
        Server::$isStart = true;
        Server::$serverStartTime = (Carbon::now())->format("Y-m-d H:i:s.u");

        $process = $this->processManager->getProcessFromId($workerId);
        $process->_onProcessStart();
    }

    /**
     * Internal Swoole "pipeMessage" callback.
     *
     * Forwards an inter-process pipe message to the current process, tagging
     * it with the source process.
     *
     * @param \Swoole\Server $server
     * @param int            $srcWorkerId worker id that sent the message
     * @param mixed          $message
     */
    public function _onPipeMessage($server, int $srcWorkerId, $message)
    {
        $this->processManager->getCurrentProcess()->_onPipeMessage($message, $this->processManager->getProcessFromId($srcWorkerId));
    }

    /**
     * Internal Swoole "workerStop" callback.
     *
     * Delegates the stop event to the matching process object.
     *
     * @param \Swoole\Server $server
     * @param int            $worker_id
     */
    public function _onWorkerStop($server, int $worker_id)
    {
        $process = $this->processManager->getProcessFromId($worker_id);
        $process->_onProcessStop();
    }


    /**
     * Hook invoked when the server has started (master process).
     */
    public abstract function onStart();

    /**
     * Hook invoked when the server is shutting down.
     */
    public abstract function onShutdown();

    /**
     * Hook invoked when a worker process exits abnormally.
     *
     * @param Process $process  the failed worker process
     * @param int     $exitCode worker exit code
     * @param int     $signal   signal that caused the exit
     */
    public abstract function onWorkerError(Process $process, int $exitCode, int $signal);

    /**
     * Hook invoked when the manager process starts.
     */
    public abstract function onManagerStart();

    /**
     * Hook invoked when the manager process stops.
     */
    public abstract function onManagerStop();

    /**
     * Start service
     *
     * @throws Exception
     */
    public function start()
    {
        if ($this->server == null) {
            throw new Exception("Please call configure first");
        }
        $this->server->start();
    }


    /**
     * Get server
     * @return \Swoole\Server|null
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Get the main server port.
     *
     * The main port is the WebSocket/HTTP/TCP port that backs the underlying
     * Swoole server instance created during configure().
     *
     * @return ServerPort
     */
    public function getMainPort(): ServerPort
    {
        return $this->mainPort;
    }


    /**
     * Iterate over all currently active client connections.
     *
     * @return Iterator|Swoole\Connection\Iterator iterable of active fds
     */
    public function getConnections(): Iterator
    {
        return $this->server->connections;
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * @param bool $configured
     */
    public function setConfigured(bool $configured): void
    {
        $this->configured = $configured;
    }

    /**
     * Get the client info of a connection.
     *
     * Once a client disconnects, Swoole's getClientInfo() returns false for
     * that fd, which would make the ClientInfo constructor throw a TypeError.
     * To keep working after disconnection (e.g. when publishing the will
     * message), we cache a snapshot of the info right before the client leaves
     * via setClientInfoSnapshot() and fall back to it here.
     *
     * @param int $fd
     * @return ClientInfo|null  live info if connected, last snapshot if gone,
     *                          or null when nothing was ever cached
     */
    public function getClientInfo(int $fd): ?ClientInfo
    {
        // Connection still alive: use Swoole's live info.
        if ($this->server->exists($fd)) {
            return new ClientInfo($this->server->getClientInfo($fd));
        }

        // Connection already gone: fall back to the cached snapshot.
        if (!empty($this->clientInfoSnapshot)) {
            return new ClientInfo($this->clientInfoSnapshot);
        }

        return null;
    }

    /**
     * Cache a snapshot of the client info.
     *
     * Call this the moment a disconnect is detected (e.g. on a DISCONNECT
     * packet or right before the WebSocket closes) so the info survives the
     * loss of the underlying fd and stays available for follow-up work.
     *
     * @param array $clientInfoSnapshot
     * @return void
     */
    public function setClientInfoSnapshot(array $clientInfoSnapshot)
    {
        $this->clientInfoSnapshot = $clientInfoSnapshot;
    }

    /**
     * Force-close a client connection.
     *
     * @param int  $fd    connection file descriptor
     * @param bool $reset true to send a TCP RST instead of a graceful close
     */
    public function closeFd(int $fd, bool $reset = false)
    {
        $this->server->close($fd, $reset);
    }

    /**
     * Send data to a client, automatically choosing the right transport.
     *
     * Looks up the client's port: if the connection is an established WebSocket
     * it pushes a WebSocket frame, otherwise it sends raw TCP data.
     *
     * @param int    $fd   connection file descriptor
     * @param string $data payload to send
     */
    public function autoSend(int $fd, string $data)
    {
        $clientInfo = $this->getClientInfo($fd);
        $port = $this->getPortManager()->getPortFromPortNo($clientInfo->getServerPort());
        if ($this->isEstablished($fd)) {
            $this->wsPush($fd, $data, $port->getPortConfig()->getWsOpcode());
        } else {
            $this->send($fd, $data);
        }
    }

    /**
     * Send data to client
     *
     * @param int $fd
     * @param string $data
     * @param int $serverSocket Need for Unix socket dgram, not needed for tcp
     * @return bool
     */
    public function send(int $fd, string $data, int $serverSocket = -1): bool
    {
        return $this->server->send($fd, $data, $serverSocket);
    }

    /**
     * Send file to client
     *
     * @param int $fd
     * @param string $filename
     * @param int $offset
     * @param int $length
     * @return bool
     */
    public function sendFile(int $fd, string $filename, int $offset = 0, int $length = 0): bool
    {
        return $this->server->sendfile($fd, $filename, $offset, $length);
    }


    /**
     * Send a UDP datagram to a remote host.
     *
     * @param string $ip            target IP address
     * @param int    $port          target port
     * @param string $data          payload to send
     * @param int    $server_socket local socket to send from (-1 = default)
     * @return bool
     */
    public function sendToUpd(string $ip, int $port, string $data, int $server_socket = -1): bool
    {
        return $this->server->sendto($ip, $port, $data, $server_socket);
    }

    /**
     * Check whether a connection fd is still alive.
     *
     * @param int $fd
     * @return bool true if the connection exists, false once it is closed
     */
    public function existFd($fd): bool
    {
        return $this->server->exist($fd);
    }

    /**
     * Bind fd to uid. set [dispatch_mode=5], all uid connection is allocated to the same worker process
     *
     * @param int $fd
     * @param int $uid
     */
    public function bindUid(int $fd, int $uid)
    {
        $this->server->bind($fd, $uid);
    }

    /**
     * Get Server stats
     *
     * @return ServerStats
     */
    public function stats(): ServerStats
    {
        return new ServerStats($this->server->stats());
    }

    /**
     * Heart beat
     *
     * @param bool $ifCloseConnection
     * @return array
     */
    public function heartbeat(bool $ifCloseConnection = true): array
    {
        return $this->server->heartbeat($ifCloseConnection);
    }


    /**
     * Get Last error
     * 1001 connection is closed by server
     * 1002 connection is closed by client
     * 1003 connection is closing
     * 1004 connection is closed
     * 1005 connection is not exists
     * 1007 receive timeout data
     * 1008 send buffer is full
     * 1202 send data length exceed Server->buffer_output_size setting
     * @return int
     */
    public function getLastError(): int
    {
        return $this->server->getLastError();
    }

    /**
     * Protect connection state, don't be disconnected by heartbeat process
     * @param int $fd
     * @param bool $value
     */
    public function protect(int $fd, bool $value = true)
    {
        $this->server->protect($fd, $value);
    }

    /**
     * Confirm connection, go with enable_delay_receive, don't listen read event, only trigger onConnection callback
     *
     * @param int $fd
     */
    public function confirm(int $fd)
    {
        $this->server->confirm($fd);
    }

    /**
     * Reload all Worker/Task process
     */
    public function reload()
    {
        $this->server->reload();
    }

    /**
     * Shut down server
     */
    public function shutdown()
    {
        $this->server->shutdown();
    }

    /**
     * Websocket push, maximum is 2M
     *
     * @param int $fd
     * @param $data
     * @param int $opcode
     * @param bool $finish
     * @return bool
     */
    public function wsPush(int $fd, $data, int $opcode = 1, bool $finish = true): bool
    {
        return $this->server->push($fd, $data, $opcode, $finish);
    }

    /**
     * Close websocket client connection
     *
     * @param int $fd
     * @param int $code
     * @param string $reason
     * @return bool
     */
    public function wsDisconnect(int $fd, int $code = 1000, string $reason = ""): bool
    {
        return $this->server->disconnect($fd, $code, $reason);
    }

    /**
     * Is available websocket connection
     *
     * @param int $fd
     * @return bool
     */
    public function isEstablished(int $fd): bool
    {
        if (is_callable([$this->server, "isEstablished"])) {
            return $this->server->isEstablished($fd);
        } else {
            return false;
        }
    }

    /**
     * Pack a payload into a raw WebSocket frame.
     *
     * @param WebSocketFrame $webSocketFrame frame holding data/opcode/finish
     * @return string raw frame bytes ready to send
     */
    public function wsPack(WebSocketFrame $webSocketFrame): string
    {
        return $this->server->pack($webSocketFrame->getData(), $webSocketFrame->getOpcode(), $webSocketFrame->getFinish());
    }

    /**
     * Unpack raw WebSocket frame bytes into a WebSocketFrame bean.
     *
     * @param string $data raw frame bytes received from the client
     * @return WebSocketFrame
     */
    public function wsUnPack(string $data): WebSocketFrame
    {
        return new WebSocketFrame($this->server->unpack($data));
    }

    /**
     * @return ProcessManager
     */
    public function getProcessManager(): ProcessManager
    {
        return $this->processManager;
    }

    /**
     * @param ProcessManager $processManager
     * @return void
     */
    public function setProcessManager(ProcessManager $processManager): void
    {
        $this->processManager = $processManager;
    }


    /**
     * @return PortManager
     */
    public function getPortManager(): PortManager
    {
        return $this->portManager;
    }

    /**
     * @return PluginInterfaceManager
     */
    public function getPlugManager(): PluginInterfaceManager
    {
        return $this->plugManager;
    }

    /**
     * @param PortManager $portManager
     * @return void
     */
    public function setPortManager(PortManager $portManager): void
    {
        $this->portManager = $portManager;
    }

    /**
     * @return Context|null
     */
    public function getContext(): ?Context
    {
        return $this->context;
    }

    /**
     * @return EventDispatcher
     * @throws Exception
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return DI::getInstance()->get(EventDispatcher::class);
    }

    /**
     * @return ServerConfig
     */
    public function getServerConfig(): ServerConfig
    {
        return $this->serverConfig;
    }


    /**
     * @return ConfigContext
     */
    public function getConfigContext(): ConfigContext
    {
        return DI::getInstance()->get(ConfigContext::class);
    }

    /**
     * @return Container|null
     */
    public function getContainer(): ?Container
    {
        return $this->container;
    }

    /**
     * @return PluginInterfaceManager
     */
    public function getBasePluginManager(): PluginInterfaceManager
    {
        return $this->basePluginManager;
    }

    /**
     * @param PluginInterfaceManager $basePluginManager
     * @return void
     */
    public function setBasePluginManager(PluginInterfaceManager $basePluginManager): void
    {
        $this->basePluginManager = $basePluginManager;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function basePluginManagerInit()
    {
        $this->basePluginManager->addPlugin(new ConfigPlugin());
        $this->basePluginManager->addPlugin(new LoggerPlugin());
        $this->basePluginManager->addPlugin(new EventPlugin());

        $this->basePluginManager->order();

        $this->basePluginManager->init($this->getContext());

        $this->basePluginManager->beforeServerStart($this->getContext());
    }

    /**
     * @param ServerConfig $serverConfig
     */
    public function setTimeZone(ServerConfig $serverConfig)
    {
        if (!empty($serverConfig->getTimeZone())) {
            $timeZone = $serverConfig->timeZone;
        } elseif (!empty(ini_get('date.timezone'))) {
            $timeZone = ini_get('date.timezone');
        } else {
            $timeZone = "UTC";
        }
        date_default_timezone_set($timeZone);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function setExceptionHandler()
    {
        set_exception_handler(function ($e) {
            $this->getLog()->error($e);
        });
    }

    /**
     * @var array In-memory receive buffers keyed by connection fd.
     *             Used to reassemble split/merged protocol packets (e.g. MQTT)
     *             across multiple frames on the same connection.
     */
    public static $buffers = [];
}