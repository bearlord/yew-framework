<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;

trait GetIpc
{
    /**
     * @param Process|null $process
     * @param string $className
     * @param bool $oneway Whether one way
     * @param float $timeOut
     * @return IpcProxy
     */
    public function callProcess(Process $process, string $className, bool $oneway = false, float $timeOut = 5): IpcProxy
    {
        return new IpcProxy($process, $className, $oneway, $timeOut);
    }

    /**
     * @param string $processName
     * @param string $className
     * @param bool $oneway
     * @param float $timeOut
     * @return IpcProxy
     * @throws IpcException
     */
    public function callProcessName(string $processName, string $className, bool $oneway = false, float $timeOut = 5): IpcProxy
    {
        $process = Server::$instance->getProcessManager()->getProcessFromName($processName);
        if ($process == null) {
            throw new IpcException("The process does not exist");
        }
        return new IpcProxy($process, $className, $oneway, $timeOut);
    }
}