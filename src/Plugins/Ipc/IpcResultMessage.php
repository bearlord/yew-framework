<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

use Yew\Core\Message\Message;

class IpcResultMessage extends Message
{
    /**
     * IpcCallMessage constructor.
     * @param int $token
     * @param $result
     * @param string|null $errorClass
     * @param int|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(int $token, $result, ?string $errorClass, ?int $errorCode, ?string $errorMessage)
    {
        parent::__construct(IpcMessageProcessor::TYPE, new IpcResultData($token, $result, $errorClass, $errorCode, $errorMessage));
    }

    /**
     * @return IpcResultData
     */
    public function getProcessRPCResultData(): IpcResultData
    {
        return $this->getData();
    }
}