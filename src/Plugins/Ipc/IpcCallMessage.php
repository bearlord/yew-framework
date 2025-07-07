<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

use Yew\Core\Message\Message;

class IpcCallMessage extends Message
{
    /**
     * @param string $className
     * @param string $name
     * @param array $arguments
     * @param bool $oneway
     */
    public function __construct(string $className, string $name, array $arguments, bool $oneway)
    {
        parent::__construct(IpcMessageProcessor::TYPE, new IpcCallData($className, $name, $arguments, $oneway));
    }

    /**
     * @return IpcCallData
     */
    public function getProcessIpcCallData(): IpcCallData
    {
        return $this->getData();
    }
}