<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */
namespace Yew\Plugins\Ipc;

use Yew\Core\Context\Context;
use Yew\Core\Message\MessageProcessor;
use Yew\Core\Plugin\AbstractPlugin;

class IpcPlugin extends AbstractPlugin
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return "processIpc";
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {

    }

    /**
     * @param Context $context
     * @return void
     * @throws \Yew\Core\Exception\Exception
     */
    public function beforeProcessStart(Context $context)
    {
        //Register event dispatch handler
        MessageProcessor::addMessageProcessor(new IpcMessageProcessor());
        $this->ready();
    }
}