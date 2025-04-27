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
    protected float $timeOut;
    /**
     * @var bool
     */
    protected bool $oneway;

    /**
     * @var int
     */
    protected int $sessionId;

    /**
     * IpcProxy constructor.
     * @param Process $process
     * @param string $className
     * @param bool $oneway
     * @param float $timeOut
     */
    public function __construct(Process $process, string $className, bool $oneway = false, float $timeOut = 0)
    {
        $this->process = $process;
        $this->className = $className;
        $this->timeOut = $timeOut;
        $this->oneway = $oneway;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws IpcException
     */
    public function __call(string $name, array $arguments)
    {
        if ($this->sessionId != null) {
            $arguments['sessionId'] = $this->sessionId;
        }
        $message = new IpcCallMessage($this->className, $name, $arguments, $this->oneway);
        if (!$this->oneway) {
            $channel = IpcManager::getChannel($message->getProcessIpcCallData()->getToken());
        }
        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage($message, $this->process);
        if (!$this->oneway) {
            $result = $channel->pop($this->timeOut);
            $channel->close();
            if ($result instanceof IpcResultData) {
                if ($result->getErrorClass() != null) {
                    throw new IpcException("[{$result->getErrorClass()}]{$result->getErrorMessage()}", $result->getErrorCode());
                } else {
                    return $result->getResult();
                }
            } else {
                throw new IpcException(Yii::t('esd', 'Time out'));
            }
        }
    }

    /**
     * Start transaction
     * @param callable $call
     * @throws \Throwable
     */
    public function startTransaction(callable $call)
    {
        if ($this->sessionId != null) return;
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
     */
    protected function _endTransaction()
    {
        if ($this->sessionId == null) return;
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