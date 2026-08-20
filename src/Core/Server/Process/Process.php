<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Process;

use Carbon\Carbon;
use Yew\Core\Channel\Channel;
use Yew\Core\Context\Context;
use Yew\Core\Context\ContextBuilder;
use Yew\Core\Context\ContextManager;
use Yew\Core\Log\LoggerInterface;
use Yew\Core\Message\Message;
use Yew\Core\Message\MessageProcessor;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Server\Server;

abstract class Process
{
    const DEFAULT_GROUP = "DefaultGroup";

    const WORKER_GROUP = "WorkerGroup";

    const SERVER_GROUP = "ServerGroup";

    const SOCK_DGRAM = 2;

    const PROCESS_TYPE_WORKER = 1;

    const PROCESS_TYPE_CUSTOM = 3;

    /**
     * Max bytes read from the IPC pipe per recv() call (equals the Swoole
     * default for an unadorned recv() and keeps the intent explicit).
     */
    const IPC_RECV_CHUNK_SIZE = 65536;

    /**
     * Hard cap on the in-memory reassembly buffer. If the peer never delivers
     * the rest of a (possibly malicious / malformed) frame, the buffer stops
     * growing here to avoid OOM; the connection is then closed.
     */
    const IPC_MAX_BUFFER_SIZE = 16 * 1024 * 1024;

    /**
     * Max declared frame length accepted from a peer. A larger value is
     * treated as a protocol error and the connection is aborted immediately
     * instead of waiting for data that will never arrive.
     */
    const IPC_MAX_FRAME_SIZE = 16 * 1024 * 1024;

    /**
     * Process type
     * @var int
     */
    protected int $processType;


    /**
     * Process id
     * @var int
     */
    protected int $processId;

    /**
     * Process pid
     * @var int
     */
    protected int $processPid;

    /**
     * Process name
     * @var string|null
     */
    protected ?string $processName;

    /**
     * @var Server
     */
    protected Server $server;

    /**
     * 进程组名
     * @var string
     */
    protected string $groupName;

    /**
     * Swoole process
     * @var \Swoole\Process|null
     */
    protected ?\Swoole\Process $swooleProcess = null;

    /**
     * @var Context|null
     */
    protected ?Context $context;

    /**
     * @var EventDispatcher
     */
    protected EventDispatcher $eventDispatcher;

    /**
     * @var \Swoole\Coroutine\Socket
     */
    private \Swoole\Coroutine\Socket $socket;

    /**
     * Bounded IPC mailbox (Swoole Channel). Received frames are pushed here by
     * the draining coroutine and popped by the single consumer coroutine. It is
     * an instance property so _onProcessStop() can close it on SIGTERM to let
     * the consumer exit gracefully. Null for worker processes (no IPC server).
     * @var \Swoole\Coroutine\Channel|null
     */
    protected ?\Swoole\Coroutine\Channel $mailbox = null;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $log;

    /**
     * @var bool
     */
    protected bool $isReady = false;

    /**
     * Channel[]
     * @var array
     */
    protected array $waitChannel = [];

    /**
     * @var int Max bytes written per Swoole\Process::write call. Swoole caps a
     *          single write on the UnixSocket pipe, so large frames are chunked.
     */
    protected int $writeChunkSize = 65535;

    /**
     * Process constructor.
     * @param Server $server
     * @param int $processId
     * @param string|null $name
     * @param string $groupName
     * @throws \Exception
     */
    public function __construct(Server $server, int $processId, string $name = null, string $groupName = self::DEFAULT_GROUP)
    {
        $this->server = $server;
        $this->groupName = $groupName;
        $this->processId = $processId;

        if ($groupName == self::WORKER_GROUP) {
            $this->processType = self::PROCESS_TYPE_WORKER;
        } else {
            $this->processType = self::PROCESS_TYPE_CUSTOM;
        }

        $this->processName = $name;

        $contextBuilder = ContextManager::getInstance()->getContextBuilder(ContextBuilder::PROCESS_CONTEXT,
            function () {
                return new ProcessContextBuilder($this);
            });
        $this->context = $contextBuilder->build();
    }

    /**
     * @param int $SIG
     * @param callable $param
     */
    public static function signal(int $SIG, callable $param)
    {
        \Swoole\Process::signal($SIG, $param);
    }

    /**
     * Create process
     *
     * @return Process
     */
    public function createProcess(): Process
    {
        $this->swooleProcess = new \Swoole\Process([$this, "_onProcessStart"], false, self::SOCK_DGRAM, true);

        return $this;
    }

    /**
     * Get swoole process
     *
     * @return \Swoole\Process
     */
    public function getSwooleProcess(): \Swoole\Process
    {
        return $this->swooleProcess;
    }

    /**
     * Get process name
     *
     * @return string
     */
    public function getProcessName(): string
    {
        return $this->processName;
    }

    /**
     * Get server
     *
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * Get group name
     *
     * @return string
     */
    public function getGroupName(): string
    {
        return $this->groupName;
    }

    /**
     * Get context
     *
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->isReady;
    }

    /**
     * @param bool $isReady
     */
    public function setIsReady(bool $isReady): void
    {
        $this->isReady = $isReady;
        foreach ($this->waitChannel as $channel) {
            $channel->close();
        }
        $this->waitChannel = [];
    }

    /**
     * Execute external command.
     *
     * @param string $path
     * @param array $params
     */
    protected function exec(string $path, array $params)
    {
        $this->swooleProcess->exec($path, $params);
    }

    /**
     * Set process name
     *
     * @param string $name
     */
    protected function setName(string $name)
    {
        $this->processName = $name;
        self::setProcessTitle(Server::$instance->getServerConfig()->getName() . "-" . $name);
    }

    /**
     * 进程启动的回调
     */
    public function _onProcessStart()
    {
        $this->log = Server::$instance->getLog();
        $this->eventDispatcher = Server::$instance->getEventDispatcher();
        try {
            Server::$isStart = true;
            if ($this->processName != null) {
                $this->setName($this->processName);
            }
            $this->server->getProcessManager()->setCurrentProcessId($this->processId);
            $this->processPid = getmypid();
            $this->server->getProcessManager()->setCurrentProcessPid($this->processPid);

            //Basic plugin initialization
            $this->server->getBasePluginManager()->beforeProcessStart($this->context);
            $this->server->getBasePluginManager()->waitReady();

            //User plugin initialization
            $this->server->getPlugManager()->beforeProcessStart($this->context);
            $this->server->getPlugManager()->waitReady();
            $this->setIsReady(true);
            $this->init();

            if ($this->getProcessType() == self::PROCESS_TYPE_CUSTOM) {
                $this->getProcessManager()->setCurrentProcessId($this->processId);
                Process::signal(SIGTERM, [$this, '_onProcessStop']);
                $this->socket = $this->swooleProcess->exportSocket();

                // Bounded mailbox + a single draining coroutine. Previously every
                // fully-reassembled frame spawned a new coroutine
                // (\Swoole\Coroutine::create), so a burst of IPC frames could
                // create an unbounded number of coroutines (memory/scheduler
                // pressure). Now received frames are pushed into a bounded
                // Swoole Channel; push() yields when the channel is full, which
                // back-pressures this receiver coroutine (it stops draining the
                // socket) and, combined with the sender-side EAGAIN retry in
                // sendMessage(), bounds the in-flight message count per process.
                $mailboxSize = 1024;
                // Promote mailbox to an instance property so _onProcessStop() can
                // close it on SIGTERM, letting the consumer coroutine exit cleanly.
                $this->mailbox = new \Swoole\Coroutine\Channel($mailboxSize);
                // Single long-lived consumer: pops frames and dispatches them.
                // Exactly one coroutine processes pipe messages at a time, so the
                // total coroutine count stays bounded at (mailboxSize + 2).
                \Swoole\Coroutine::create(function () {
                    while (true) {
                        $item = $this->mailbox->pop();
                        // Channel closed (SIGTERM) or error: exit safely.
                        if ($item === false) {
                            break;
                        }
                        [$payload, $fromProcess] = $item;
                        $this->_onPipeMessage(serverUnSerialize($payload), $fromProcess);
                    }
                });

                \Swoole\Coroutine::create(function () {
                    // Frames are streamed over the UnixSocket without message
                    // boundaries, so we accumulate bytes and reassemble complete
                    // frames ourselves. Layout: [4B srcProcessId][4B payloadLen][payload].
                    // Hard cap on the reassembly buffer so a peer advertising a huge
                    // frame length (or a protocol error) cannot grow $buffer unbounded.
                    $maxBufferSize = self::IPC_MAX_BUFFER_SIZE;
                    $maxFrameSize = self::IPC_MAX_FRAME_SIZE;
                    $buffer = '';
                    while (true) {
                        $recv = $this->socket->recv(self::IPC_RECV_CHUNK_SIZE);
                        if ($recv === '') {
                            // Peer closed the pipe (process exited / crashed): stop.
                            break;
                        }
                        if ($recv === false) {
                            // A fatal read error (not EAGAIN — under enableCoroutine
                            // the coroutine socket auto-suspends on EAGAIN and never
                            // returns false for it). Stop receiving.
                            break;
                        }

                        $buffer .= $recv;
                        // Total reassembly buffer exceeded: drop the pending bytes
                        // to bound memory, but KEEP the draining coroutine alive.
                        // Breaking here would kill the only receiver for this
                        // process and silently stop ALL IPC to it (every caller
                        // would then time out) — far worse than a transient
                        // back-pressure event under burst load. We keep the last 7
                        // bytes so a header straddling the boundary is not lost.
                        if (strlen($buffer) > $maxBufferSize) {
                            $this->log->warning(
                                "IPC reassembly buffer exceeded {$maxBufferSize} bytes, "
                                . "dropping pending bytes (receiver coroutine stays alive)"
                            );
                            $buffer = substr($buffer, -7);
                            \Swoole\Coroutine::sleep(0.001);
                            continue;
                        }
                        while (strlen($buffer) >= 8) {
                            $header = unpack("Nid/Nlen", substr($buffer, 0, 8));
                            // Declared frame length is implausibly large -> a
                            // malformed/truncated header. Drop only this 8-byte
                            // header and resync on the remaining bytes instead of
                            // aborting the whole receiver.
                            if ($header['len'] > $maxFrameSize) {
                                $this->log->warning(
                                    "IPC frame length {$header['len']} exceeds cap "
                                    . "{$maxFrameSize}, dropping malformed header"
                                );
                                $buffer = substr($buffer, 8);
                                continue;
                            }
                            $frameSize = 8 + $header['len'];
                            if (strlen($buffer) < $frameSize) {
                                break; // frame not fully arrived yet
                            }

                            $processId = $header['id'];
                            $payload = substr($buffer, 8, $header['len']);
                            $buffer = substr($buffer, $frameSize);

                            $fromProcess = $this->server->getProcessManager()->getProcessFromId($processId);
                            // push() yields (and thus bounds concurrency) when the
                            // mailbox is full instead of spawning a new coroutine.
                            $this->mailbox->push([$payload, $fromProcess]);
                        }
                    }
                    // Socket disconnected: close mailbox so the consumer exits.
                    if (isset($this->mailbox) && !$this->mailbox->isClosed()) {
                        $this->mailbox->close();
                    }
                });
            }

            enableRuntimeCoroutine();

            //Dispatch event
            $this->eventDispatcher->dispatchEvent(new ProcessEvent(ProcessEvent::ProcessStartEvent, $this));
            $this->onProcessStart();
        } catch (\Throwable $e) {
            $this->log->error($e);
        }
    }

    public abstract function init();

    /**
     * On pipe message
     *
     * @param Message $message
     * @param Process $fromProcess
     */
    public function _onPipeMessage(Message $message, Process $fromProcess)
    {
        $this->waitReady();
        try {
            if (!MessageProcessor::dispatch($message)) {
                $this->onPipeMessage($message, $fromProcess);
            }
        } catch (\Throwable $e) {
            $this->log->error($e);
        }
    }

    /**
     * On process stop.
     */
    public function _onProcessStop()
    {
        try {
            // Close the mailbox first so the consumer coroutine (blocked in
            // $mailbox->pop()) wakes up with false and exits cleanly instead of
            // lingering until the process is force-killed.
            if ($this->mailbox !== null && !$this->mailbox->isClosed()) {
                $this->mailbox->close();
            }
            // Dispatch event
            $this->eventDispatcher->dispatchEvent(new ProcessEvent(ProcessEvent::ProcessStopEvent, $this));
            $this->onProcessStop();
        } catch (\Throwable $e) {
            $this->log->error($e);
        }
        if ($this->swooleProcess != null) {
            $this->swooleProcess->exit();
        }
    }

    /**
     * Send message to the process
     *
     * @param Message|mixed $message
     * @param Process $toProcess
     */
    public function sendMessage(Message $message, Process $toProcess)
    {
        //If send to self
        if ($this->getProcessId() == $toProcess->getProcessId()) {
            $this->_onPipeMessage($message, $this);
            return;
        }
        if ($toProcess->getProcessType() == self::PROCESS_TYPE_CUSTOM) {
            // Always serialize so the receiver can reliably unserialize, then
            // wrap in a length-prefixed frame: [4B srcProcessId][4B payloadLen][payload].
            // The receiver reassembles by length, so a single write() need not
            // carry the whole frame — we chunk it to honour Swoole's pipe limit.
            $payload = serverSerialize($message);
            $frame = pack("N", $this->getProcessId()) . pack("N", strlen($payload)) . $payload;
            $offset = 0;
            $len = strlen($frame);
            // Swoole's Unix pipe has a bounded kernel buffer. Under burst load the
            // actor/worker consumer cannot keep up and write() returns false with
            // EAGAIN (Resource temporarily unavailable). The original loop ignored
            // the return value and silently dropped frames, leaving the caller
            // waiting forever on a reply that was never delivered (=> IPC Timeout).
            // We now retry on EAGAIN with a short coroutine yield, bounded by a
            // hard cap well below the 5s IPC timeout so a stuck pipe fails loud
            // instead of hanging the caller.
            $maxRetries = 2000; // ~2s of 1ms yields, leaves headroom vs 5s IPC timeout
            while ($offset < $len) {
                $written = @$toProcess->swooleProcess->write(substr($frame, $offset, $this->writeChunkSize));
                if ($written === false) {
                    $err = swoole_last_error();
                    if ($err === SOCKET_EAGAIN || $err === SOCKET_EWOULDBLOCK) {
                        if ($maxRetries-- <= 0) {
                            throw new \RuntimeException(sprintf(
                                'Process::sendMessage to %s failed: pipe buffer full after retries (frame %d/%d bytes)',
                                $toProcess->getProcessName(),
                                $offset,
                                $len
                            ));
                        }
                        \Swoole\Coroutine::sleep(0.001);
                        continue;
                    }
                    throw new \RuntimeException(sprintf(
                        'Process::sendMessage to %s failed: write error %d',
                        $toProcess->getProcessName(),
                        $err
                    ));
                }
                $offset += $written;
            }
        } else {
            //If process is worker or task
            $this->server->getServer()->sendMessage($message, $toProcess->getProcessId());
        }
    }

    /**
     * Process start
     *
     * @return mixed
     */
    public abstract function onProcessStart();

    /**
     * On process stop
     *
     * @return mixed
     */
    public abstract function onProcessStop();

    /**
     * On pipe message
     *
     * @param Message $message
     * @param Process $fromProcess
     * @return mixed
     */
    public abstract function onPipeMessage(Message $message, Process $fromProcess);

    /**
     * @return int
     */
    public function getProcessType(): int
    {
        return $this->processType;
    }

    /**
     * @return int
     */
    public function getProcessId(): int
    {
        return $this->processId;
    }

    /**
     * @return int
     */
    public function getProcessPid(): int
    {
        return $this->processPid;
    }

    /**
     * Get process manager
     *
     * @return ProcessManager
     */
    public function getProcessManager(): ProcessManager
    {
        return $this->server->getProcessManager();
    }

    /**
     * Is darwin
     *
     * @return bool
     */
    public static function isDarwin(): bool
    {
        if (PHP_OS == "Darwin") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set process name.
     *
     * @param string $title
     * @return void
     */
    public static function setProcessTitle(string $title)
    {
        if (self::isDarwin()) {
            return;
        }
        // >=php 5.5
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        else {
            @swoole_set_process_name($title);
        }
    }

    /**
     * Wait ready
     */
    public function waitReady()
    {
        if ($this->isReady()) {
            return;
        }
        $channel = DIGet(Channel::class);
        $this->waitChannel[] = $channel;
        $channel->pop();
    }
}
