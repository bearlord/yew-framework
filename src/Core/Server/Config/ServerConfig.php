<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Config;

use ReflectionException;
use Yew\Core\Exception\Exception;
use Yew\Core\Exception\ConfigException;
use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Core\Runtime;

class ServerConfig extends BaseConfig
{
    const key = "yew.server";

    /**
     * Server name
     * @var string
     */
    protected string $name = "yew";

    /**
     * Root directory
     * @var string
     */
    protected string $rootDir = "";

    /**
     * Number of reactor threads. Use this parameter to adjust the number of Reactor threads to take full advantage of multi-core.
     * @var int|null
     */
    protected ?int $reactorNum = null;

    /**
     * Number of worker processes, set the number of started worker processes
     * @var int|null
     */
    protected ?int $workerNum = null;

    /**
     * Data packet distribution strategy. 7 types can be selected, default is 2
     * 1, round-robin mode, will receive round-robin allocation to each Worker process
     * 2, fixed mode, worker is allocated according to the connected file descriptor. This ensures that data sent from the same connection will only be processed by the same worker.
     * 3, preemption mode, the main process will choose to deliver according to the busy and idle status of the worker, and will only deliver to the worker in the idle state
     * 4, IP allocation, modulo hash based on client IP, assigned to a fixed Worker process. It can be guaranteed that the connection data of the same source IP will always be allocated to the same Worker process. The algorithm is ip2long (ClientIP)% worker_num
     * 5, UID assignment, need to call Server-> bind () in user code to bind one connection to one uid. The bottom layer is then allocated to different Worker processes based on the UID value. The algorithm is UID% worker_num, if you need to use a string as the UID, you can use crc32 (UID_STRING)
     * 7, stream mode, idle workers will accept connections and accept new requests from Reactor
     * @var int
     */
    protected int $dispatchMode = 2;

    /**
     * @var int Maximum Request
     */
    protected int $maxRequest = 0;

    /**
     * Maximum connection
     * @var int|null
     */
    protected ?int $maxConn = null;

    /**
     * @var string|null
     */
    protected ?string $proxyServerClass = null;

    /**
     * Daemonize => 1, after adding this parameter, it will be transferred to the background and run as a daemon
     * @var bool
     */
    protected bool $daemonize = false;

    /**
     * Set the asynchronous restart switch. When set to true, the asynchronous safe restart feature will be enabled,
     * and the worker process will wait for the asynchronous event to complete before exiting
     * @var bool
     */
    protected bool $reloadAsync = true;

    /**
     * The maximum waiting time after the Worker process receives the notification of stopping the service.
     * The default is 3 seconds.
     * @var int
     */
    protected int $maxWaitTime = 3;

    /**
     * CPU affinity setting open_cpu_affinity => 1, enable CPU affinity setting
     * On multi-core hardware platforms, enabling this feature will bind the swoole's reactor thread/worker process to a fixed core.
     * Can avoid switching between multiple cores when the process/thread is running, and improve the hit rate of the CPU Cache.
     * @var bool
     */
    protected bool $openCpuAffinity = false;

    /**
     * Accepts an array as a parameter, array (0, 1) means that CPU0 and CPU1 are not used,
     * and they are specifically set aside to handle network interrupts.
     * @var array
     */
    protected array $cpuAffinityIgnore = [];

    /**
     * log_file => '/data/log/swoole.log', Specify the swoole error log file. The exception information that occurred
     * during the swoole runtime will be recorded in this file. It is printed to the screen by default.
     * @var string
     */
    protected string $logFile = "";

    /**
     * Set the level of server error log printing. The range is 0-5. Log messages below the log_level setting are not thrown.
     * 0 => SWOOLE_LOG_DEBUG
     * 1 => SWOOLE_LOG_TRACE
     * 2 => SWOOLE_LOG_INFO
     * 3 => SWOOLE_LOG_NOTICE
     * 4 => SWOOLE_LOG_WARNING
     * 5 => SWOOLE_LOG_ERROR
     * SWOOLE_LOG_DEBUG and SWOOLE_LOG_TRACE are only available when compiled into --enable-debug-log and --enable-trace-log versions
     * The default is SWOOLE_LOG_DEBUG, that is, all levels are printed
     * @var int
     */
    protected int $logLevel = 0;

    /**
     * Heartbeat detection mechanism Every second, Swoole polls all TCP connections
     * and closes connections that exceed the heartbeat time.
     * @var int|bool
     */
    protected $heartbeatCheckInterval;

    /**
     * Heartbeat detection mechanism The maximum idle time of a TCP connection, in units of s.
     * If the last packet sent by a fd is longer than the heartbeat_idle_time, the connection will be closed.
     * @var int|null
     */
    protected ?int $heartbeatIdleTime = null;

    /**
     * Set the user of the Worker / TaskWorker child process.
     * If the server needs to listen to ports below 1024, it must have root privileges.
     * But the program runs under the root user. Once there is a vulnerability in the code, an attacker can
     * execute remote instructions as root, which is very risky.
     * After the user item is configured, the main process can run under root authority, and the child
     * process can run under ordinary user authority.
     * @var string
     */
    protected string $user = "";

    /**
     * Set the process user group for the worker child process. Same as the user configuration,
     * this configuration is to modify the user group to which the process belongs to improve the security of the server program.
     * @var string
     */
    protected string $group = "";

    /**
     * Redirect the file system root of the Worker process.
     * This setting isolates processes from reading and writing to the file system from the actual operating
     * system file system. Improve security.
     * @var string
     */
    protected string $chroot = "";

    /**
     * The PID of the master process is automatically written to the file when the server starts,
     * and the PID file is automatically deleted when the server is shut down.
     * @var string
     */
    protected string $pidFile = "";

    /**
     * Configure the send output buffer memory size.
     * Note that this function should not be adjusted too large, to avoid too much congested data,
     * which will cause the memory of the machine to be used up.
     * When starting a large number of Worker processes, it will take up worker_num * buffer_output_size bytes of memory
     * @var int|null
     */
    protected ?int $bufferOutputSize = null;

    /**
     * Data sending buffer area
     * The parameter buffer_output_size is used to set the single maximum sending length. socket_buffer_size is used to
     * set the maximum amount of memory allowed for client connections.
     * Adjust the size of the connection send buffer area. TCP communication has a congestion control mechanism.
     * When a server sends a large amount of data to a client, it cannot send it immediately.
     * The data sent at this time will be stored in the server's memory buffer area.
     * This parameter can adjust the size of the memory cache area.
     * If too much data is sent and the client is blocked, the server will report the following error message after the
     * data fills the buffer area: swFactoryProcess_finish: send failed, session # 1 output buffer has been overflowed.
     * The send buffer is full and send fails, which will only affect the current client, other clients will not be affected
     * When the server has a large number of TCP connections, it will occupy serv->max_connection in the worst case.
     * @var int|null
     */
    protected ?int $socketBufferSize = null;

    /**
     * Sets the maximum number of coroutines for the current worker process.
     * Beyond max_coroutine, the bottom layer will not be able to create a new coroutine,
     * the bottom layer will throw an error and close the connection directly.
     * @var int
     */
    protected int $maxCoroutine = 100000;

    /**
     * Uploaded files Temporary directory.
     * @var string
     */
    protected string $uploadTmpDir = "";

    /**
     * Set the POST message parsing switch. When the option is true, the request body whose Content-Type is
     * x-www-form-urlencoded is automatically parsed into the POST array. When set to false, POST parsing is turned off.
     * @var bool
     */
    protected bool $httpParsePost = true;

    /**
     * After $enableStaticHandler is true, the underlying layer will first determine whether the file exists in
     * the document_root path after receiving the Http request. If it exists, it will directly send the file content to
     * the client and will not trigger the onRequest callback
     * @var bool
     */
    protected bool $enableStaticHandler = false;

    /**
     * Static file root directory for use with $enableStaticHandler.
     * @var string
     */
    protected string $documentRoot = "";


    /**
     * Enable http compression. On by default.
     * @var bool
     */
    protected bool $httpCompression = true;

    /**
     * Set the WebSocket subprotocol. After setting the Http header of the handshake response, Sec-WebSocket-Protocol:
     * {$websocket_subprotocol} will be added. For details, please refer to the RFC document related to the WebSocket protocol.
     * @var string
     */
    protected string $websocketSubprotocol = "";

    /**
     * Enable the close frame (frame with opcode 0x08) in the websocket protocol to be received in the onMessage callback.
     * The default is false.
     * After opening, you can receive the close frame sent by the client or server in the onMessage callback in WebSocketServer,
     * and developers can handle it by themselves.
     * @var bool
     */
    protected bool $openWebsocketCloseFrame = false;

    /**
     * The default is debug mode, which means that restarting the cache is invalid.
     * @var bool
     */
    protected bool $debug = true;

    /**
     * Banner
     * @var string
     */
    protected string $banner = "";

    /**
     * Time zone
     * @var string
     */
    public string $timeZone = 'Asia/Shanghai';

    /**
     * ServerConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::key);
    }

    /**
     * @return int
     */
    public function getReactorNum(): ?int
    {
        return $this->reactorNum;
    }

    /**
     * @param int $reactorNum
     */
    public function setReactorNum(int $reactorNum): void
    {
        $this->reactorNum = $reactorNum;
    }

    /**
     * @return int
     */
    public function getWorkerNum(): int
    {
        return $this->workerNum ?? 1;
    }

    /**
     * @param int $workerNum
     */
    public function setWorkerNum(int $workerNum): void
    {
        $this->workerNum = $workerNum;
    }

    /**
     * @return int
     */
    public function getDispatchMode(): int
    {
        return $this->dispatchMode ?? 2;
    }

    /**
     * @param int $dispatchMode
     */
    public function setDispatchMode(int $dispatchMode): void
    {
        $this->dispatchMode = $dispatchMode;
    }

    /**
     * @return int
     */
    public function getMaxRequest(): int
    {
        return $this->maxRequest;
    }

    /**
     * @param int $maxRequest
     */
    public function setMaxRequest(int $maxRequest): void
    {
        $this->maxRequest = $maxRequest;
    }

    /**
     * @return int
     */
    public function getMaxConn(): int
    {
        return $this->maxConn ?? 100000;
    }

    /**
     * @param int $maxConn
     */
    public function setMaxConn(int $maxConn): void
    {
        $this->maxConn = $maxConn;
    }

    /**
     * @return bool
     */
    public function isDaemonize(): bool
    {
        return $this->daemonize ?? false;
    }

    /**
     * @param bool $daemonize
     */
    public function setDaemonize(bool $daemonize): void
    {
        $this->daemonize = $daemonize;
    }

    /**
     * @return bool
     */
    public function isReloadAsync(): bool
    {
        return $this->reloadAsync ?? false;
    }

    /**
     * @param bool $reloadAsync
     */
    public function setReloadAsync(bool $reloadAsync): void
    {
        $this->reloadAsync = $reloadAsync;
    }

    /**
     * @return int
     */
    public function getMaxWaitTime(): int
    {
        return $this->maxWaitTime ?? 30;
    }

    /**
     * @param int $maxWaitTime
     */
    public function setMaxWaitTime(int $maxWaitTime): void
    {
        $this->maxWaitTime = $maxWaitTime;
    }

    /**
     * @return bool
     */
    public function isOpenCpuAffinity(): bool
    {
        return $this->openCpuAffinity ?? true;
    }

    /**
     * @param bool $openCpuAffinity
     */
    public function setOpenCpuAffinity(bool $openCpuAffinity): void
    {
        $this->openCpuAffinity = $openCpuAffinity;
    }

    /**
     * @return array|null
     */
    public function getCpuAffinityIgnore(): ?array
    {
        return $this->cpuAffinityIgnore;
    }

    /**
     * @param array $cpuAffinityIgnore
     */
    public function setCpuAffinityIgnore(array $cpuAffinityIgnore): void
    {
        $this->cpuAffinityIgnore = $cpuAffinityIgnore;
    }

    /**
     * @return string|null
     */
    public function getLogFile(): ?string
    {
        return $this->logFile;
    }

    /**
     * @param string $logFile
     */
    public function setLogFile(string $logFile): void
    {
        $this->logFile = $logFile;
    }

    /**
     * @return int
     */
    public function getLogLevel(): int
    {
        return $this->logLevel ?? 0;
    }

    /**
     * @param int $logLevel
     */
    public function setLogLevel(int $logLevel): void
    {
        $this->logLevel = $logLevel;
    }

    /**
     * @return int|bool
     */
    public function getHeartbeatCheckInterval()
    {
        return $this->heartbeatCheckInterval;
    }

    /**
     * @param $heartbeatCheckInterval
     */
    public function setHeartbeatCheckInterval($heartbeatCheckInterval): void
    {
        $this->heartbeatCheckInterval = $heartbeatCheckInterval;
    }

    /**
     * @return int
     */
    public function getHeartbeatIdleTime(): int
    {
        return $this->heartbeatIdleTime;
    }

    /**
     * @param int $heartbeatIdleTime
     */
    public function setHeartbeatIdleTime(int $heartbeatIdleTime): void
    {
        $this->heartbeatIdleTime = $heartbeatIdleTime;
    }


    /**
     * @return string|null
     */
    public function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    /**
     * @return string|null
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @param string $group
     */
    public function setGroup(string $group): void
    {
        $this->group = $group;
    }

    /**
     * @return string|null
     */
    public function getChroot(): ?string
    {
        return $this->chroot;
    }

    /**
     * @param string $chroot
     */
    public function setChroot(string $chroot): void
    {
        $this->chroot = $chroot;
    }

    /**
     * @return string|null
     */
    public function getPidFile(): ?string
    {
        return $this->pidFile;
    }

    /**
     * @param string $pidFile
     */
    public function setPidFile(string $pidFile): void
    {
        $this->pidFile = $pidFile;
    }

    /**
     * @return int
     */
    public function getBufferOutputSize(): int
    {
        return $this->bufferOutputSize ?? 8 * 1024 * 1024;
    }

    /**
     * @param int $bufferOutputSize
     */
    public function setBufferOutputSize(int $bufferOutputSize): void
    {
        $this->bufferOutputSize = $bufferOutputSize;
    }

    /**
     * @return int|null
     */
    public function getSocketBufferSize(): ?int
    {
        return $this->socketBufferSize;
    }

    /**
     * @param int $socketBufferSize
     */
    public function setSocketBufferSize(int $socketBufferSize): void
    {
        $this->socketBufferSize = $socketBufferSize;
    }

    /**
     * @return int
     */
    public function getMaxCoroutine(): int
    {
        return $this->maxCoroutine ?? 100000;
    }

    /**
     * @param int $maxCoroutine
     */
    public function setMaxCoroutine(int $maxCoroutine): void
    {
        $this->maxCoroutine = $maxCoroutine;
    }

    /**
     * @return string|null
     */
    public function getUploadTmpDir(): ?string
    {
        return $this->uploadTmpDir;
    }

    /**
     * @param string $uploadTmpDir
     */
    public function setUploadTmpDir(string $uploadTmpDir): void
    {
        $this->uploadTmpDir = $uploadTmpDir;
    }

    /**
     * @return bool
     */
    public function isHttpParsePost(): bool
    {
        return $this->httpParsePost;
    }

    /**
     * @param bool $httpParsePost
     */
    public function setHttpParsePost(bool $httpParsePost): void
    {
        $this->httpParsePost = $httpParsePost;
    }

    /**
     * @return string|null
     */
    public function getDocumentRoot(): ?string
    {
        return $this->documentRoot;
    }

    /**
     * @param string $documentRoot
     */
    public function setDocumentRoot(string $documentRoot): void
    {
        $this->documentRoot = $documentRoot;
    }

    /**
     * @return bool
     */
    public function isEnableStaticHandler(): bool
    {
        return $this->enableStaticHandler;
    }

    /**
     * @param bool $enableStaticHandler
     */
    public function setEnableStaticHandler(bool $enableStaticHandler): void
    {
        $this->enableStaticHandler = $enableStaticHandler;
    }

    /**
     * @return bool
     */
    public function isHttpCompression(): bool
    {
        return $this->httpCompression;
    }

    /**
     * @param bool $httpCompression
     */
    public function setHttpCompression(bool $httpCompression): void
    {
        $this->httpCompression = $httpCompression;
    }

    /**
     * @return string|null
     */
    public function getWebsocketSubprotocol(): ?string
    {
        return $this->websocketSubprotocol;
    }

    /**
     * @param string $websocketSubprotocol
     */
    public function setWebsocketSubprotocol(string $websocketSubprotocol): void
    {
        $this->websocketSubprotocol = $websocketSubprotocol;
    }

    /**
     * @return bool
     */
    public function isOpenWebsocketCloseFrame(): bool
    {
        return $this->openWebsocketCloseFrame;
    }

    /**
     * @param bool $openWebsocketCloseFrame
     */
    public function setOpenWebsocketCloseFrame(bool $openWebsocketCloseFrame): void
    {
        $this->openWebsocketCloseFrame = $openWebsocketCloseFrame;
    }

    /**
     * @return array
     * @throws ConfigException
     * @throws Exception
     * @throws ReflectionException
     */
    public function buildConfig(): array
    {
        $this->merge();
        $build = [];
        if (empty($this->getRootDir())) {
            throw new ConfigException("RootDir cannot be empty");
        } else {
            //格式化rootDir
            $this->rootDir = realpath($this->getRootDir());
            if ($this->rootDir === false) {
                throw new ConfigException("RootDir does not exist");
            }
        }
        if ($this->getReactorNum() != null && $this->getReactorNum() > 0) {
            $build['reactor_num'] = $this->getReactorNum();
        }

        if ($this->getWorkerNum() != null && $this->getWorkerNum() > 0) {
            $build['worker_num'] = $this->getWorkerNum();
        } else {
            throw new ConfigException("ServerConfig WorkerNum cannot be empty or less than 1");
        }

        if ($this->getDispatchMode() != null && $this->getDispatchMode() > 0) {
            $build['dispatch_mode'] = $this->getDispatchMode();
        } else {
            throw new ConfigException("ServerConfig dispatchMode cannot be empty or less than 1");
        }

        if ($this->getMaxRequest() != null && $this->getMaxRequest() > 0) {
            $build['max_request'] = $this->getMaxRequest();
        }

        if ($this->getMaxConn() != null && $this->getMaxConn() > 0) {
            $build['max_connection'] = $this->getMaxConn();
        } else {
            throw new ConfigException("ServerConfig maxConn cannot be empty or less than 1");
        }

        $build['daemonize'] = $this->isDaemonize();

        $build['reload_async'] = $this->isReloadAsync();

        $build['max_wait_time'] = $this->getMaxWaitTime();

        $build['open_cpu_affinity'] = $this->isOpenCpuAffinity();

        if (!empty($this->getCpuAffinityIgnore())) {
            $build['cpu_affinity_ignore'] = $this->getCpuAffinityIgnore();
        }
        if (empty($this->getLogFile())) {
            $path = $this->rootDir . DIRECTORY_SEPARATOR . "runtime" . DIRECTORY_SEPARATOR . "logs";
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $this->logFile = $path . DIRECTORY_SEPARATOR . "swoole.log";
        }

        $build['log_file'] = $this->getLogFile();

        $build['log_level'] = $this->getLogLevel();

        if ($this->getHeartbeatCheckInterval() != null) {
            $build['heartbeat_check_interval'] = $this->getHeartbeatCheckInterval();
            if ($this->getHeartbeatIdleTime() != null) {
                $build['heartbeat_idle_time'] = $this->getHeartbeatIdleTime();
            }
        }

        if (!empty($this->getUser())) {
            $build['user'] = $this->getUser();
        }

        if (!empty($this->getGroup())) {
            $build['group'] = $this->getGroup();
        }

        if (!empty($this->getChroot())) {
            $build['chroot'] = $this->getChroot();
        }

        if (empty($this->getPidFile())) {
            $path = $this->rootDir . DIRECTORY_SEPARATOR . "runtime";
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $this->pidFile = $path . DIRECTORY_SEPARATOR . "pid";
        }

        $build['pid_file'] = $this->getPidFile();

        if (!empty($this->getBufferOutputSize())) {
            $build['buffer_output_size'] = $this->getBufferOutputSize();
        }

        if (!empty($this->getSocketBufferSize())) {
            $build['socket_buffer_size'] = $this->getSocketBufferSize();
        }

        if (!empty($this->getMaxCoroutine())) {
            $build['max_coroutine'] = $this->getMaxCoroutine();
        }

        if (!empty($this->getUploadTmpDir())) {
            $build['upload_tmp_dir'] = $this->getUploadTmpDir();
        }

        $build['http_parse_post'] = $this->isHttpParsePost();

        if ($this->isEnableStaticHandler()) {
            $build['enable_static_handler'] = $this->isEnableStaticHandler();
            ConfigException::AssertNull($this, "documentRoot", $this->getDocumentRoot());
            $build['document_root'] = $this->getDocumentRoot();
        }

        $build['http_compression'] = $this->isHttpCompression();

        if (!empty($this->getWebsocketSubprotocol())) {
            $build['websocket_subprotocol'] = $this->getWebsocketSubprotocol();
        }

        $build['open_websocket_close_frame'] = $this->isOpenWebsocketCloseFrame();

        $build['enable_coroutine'] = Runtime::$enableCoroutine;
        return $build;
    }

    /**
     * @return string|null
     */
    public function getBanner(): ?string
    {
        return $this->banner;
    }

    /**
     * @param string $banner
     */
    public function setBanner(string $banner): void
    {
        $this->banner = $banner;
    }

    /**
     * @return string
     */
    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    /**
     * @param string $timeZone
     */
    public function setTimeZone(string $timeZone): void
    {
        $this->timeZone = $timeZone;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getRootDir(): string
    {
        if (empty($this->rootDir) && !defined("ROOT_DIR")) {
            throw new Exception("ROOT_DIR constant is not set, please define");
        }
        if (defined("ROOT_DIR")) {
            return ROOT_DIR;
        }
        return $this->rootDir;
    }

    /**
     * Set root directory
     *
     * @param string $rootDir
     */
    public function setRootDir(string $rootDir): void
    {
        if (!defined("ROOT_DIR")) {
            $this->rootDir = $rootDir;
            define("ROOT_DIR", $rootDir);
        } else {
            $this->rootDir = ROOT_DIR;
        }
    }

    /**
     * Get Bin directory
     *
     * @return string
     * @throws Exception
     */
    public function getBinDir(): string
    {
        return realpath($this->getRootDir()) . DIRECTORY_SEPARATOR . "bin";
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getRuntimeDir(): string
    {
        return realpath($this->getRootDir()) . DIRECTORY_SEPARATOR . "runtime";
    }

    /**
     * Get cache directory
     *
     * @return string
     * @throws Exception
     */
    public function getCacheDir(): string
    {
        return $this->getBinDir() . DIRECTORY_SEPARATOR . "cache";
    }

    /**
     * Get log dir
     *
     * @return string
     * @throws Exception
     */
    public function getLogDir(): string
    {
        return $this->getBinDir() . DIRECTORY_SEPARATOR . "logs";
    }

    /**
     * Get src directory
     *
     * @return string
     * @throws Exception
     */
    public function getSrcDir(): string
    {
        return realpath($this->getRootDir()) . DIRECTORY_SEPARATOR . "src";
    }

    /**
     * Get vendor directory
     *
     * @return string
     * @throws Exception
     */
    public function getVendorDir(): string
    {
        return realpath($this->getRootDir()) . DIRECTORY_SEPARATOR . "vendor";
    }

    /**
     * Get proxy server class
     *
     * @return string|null
     */
    public function getProxyServerClass(): ?string
    {
        return $this->proxyServerClass;
    }

    /**
     * Set proxy server class
     *
     * @param string|null $proxyServerClass
     */
    public function setProxyServerClass(?string $proxyServerClass): void
    {
        $this->proxyServerClass = $proxyServerClass;
    }

    /**
     * Is debug
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set debug
     *
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

}
