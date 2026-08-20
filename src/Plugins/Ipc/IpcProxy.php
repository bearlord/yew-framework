<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;

class IpcProxy
{
    /**
     * @var Process
     */
    protected Process $process;
    /**
     * @var string
     */
    protected string $className;
    /**
     * @var float
     */
    protected float $timeOut = 0.0;
    /**
     * @var bool
     */
    protected bool $oneway;

    /**
     * @var int|null
     */
    protected ?int $sessionId = null;

    /**
     * IpcProxy constructor.
     * @param Process $process
     * @param string $className
     * @param bool $oneway
     * @param float $timeOut
     */
    public function __construct(Process $process, string $className, bool $oneway = false, float $timeOut = 5)
    {
        $this->process = $process;
        $this->className = $className;
        $this->timeOut = $timeOut;
        $this->oneway = $oneway;
    }

    /**
     * Safe debug telemetry. Logger may be null in some processes; never let it
     * crash the IPC call path.
     */
    private function telemetry(string $msg): void
    {
        try {
            $logger = Server::$instance->getLog();
            if ($logger !== null) {
                $logger->log(\Monolog\Logger::DEBUG, $msg, []);
            }
        } catch (\Throwable $e) {
            // swallow
        }
    }

    /**
     * Safe error telemetry (used for the timeout event).
     */
    private function telemetryErr(string $msg): void
    {
        try {
            $logger = Server::$instance->getLog();
            if ($logger !== null) {
                $logger->log(\Monolog\Logger::ERROR, $msg, []);
            }
        } catch (\Throwable $e) {
            // swallow
        }
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|void
     * @throws IpcException
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        if ($this->sessionId != null) {
            $arguments["sessionId"] = $this->sessionId;
        }
        $message = new IpcCallMessage($this->className, $name, $arguments, $this->oneway);
        $token = $message->getProcessIpcCallData()->getToken();

        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $this->process);
        Server::$instance->getLog()->debug(sprintf(
            "[ipc-telemetry] SENT token=%d method=%s target=%s",
            $token, $name, $this->process->getProcessName() ?? $this->process->getProcessId()
        ));

        if (!$this->oneway) {
            $channel = IpcManager::getChannel($token);
            $tWait = microtime(true);
            $result = $channel->pop($this->timeOut);
            $waitMs = (microtime(true) - $tWait) * 1000;
            if ($result === null) {
                Server::$instance->getLog()->debug(sprintf(
                    "[ipc-telemetry] TIMEOUT token=%d method=%s waited=%.2fms target=%s",
                    $token, $name, $waitMs, $this->process->getProcessName() ?? $this->process->getProcessId()
                ));
            } else {
                Server::$instance->getLog()->debug(sprintf(
                    "[ipc-telemetry] REPLIED token=%d method=%s waited=%.2fms",
                    $token, $name, $waitMs
                ));
            }
            $channel->close();

            if ($result instanceof IpcResultData) {
                if ($result->getErrorClass() != null) {
                    throw new IpcException("[{$result->getErrorClass()}]{$result->getErrorMessage()}", $result->getErrorCode());
                } else {
                    return $result->getResult();
                }
            } else {
                throw new IpcException("Time out");
            }
        }
    }

    /**
     * Start transaction
     * @param callable $call
     * @return void
     * @throws IpcException
     */
    public function startTransaction(callable $call)
    {
        if ($this->sessionId != null) {
            return;
        }
        $oneway = $this->oneway;
        $this->oneway = false;

        try {
            $this->sessionId = $this->__call("__getSession", []);
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            $this->oneway = $oneway;
        }

        try {
            $call();
        } catch (\Throwable $e) {
            $this->_endTransaction();
        } finally {
            $this->_endTransaction();
        }

    }

    /**
     * End transaction
     *
     * @return void
     * @throws IpcException
     */
    protected function _endTransaction()
    {
        if ($this->sessionId == null) {
            return;
        }
        $oneway = $this->oneway;
        $this->oneway = false;
        try {
            $this->__call("__clearSession", []);
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            $this->oneway = $oneway;
        }

        $this->sessionId = null;
    }
}
