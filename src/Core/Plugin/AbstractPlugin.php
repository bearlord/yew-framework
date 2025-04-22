<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugin;

use Yew\Core\Channel\Channel;
use Yew\Core\Context\Context;
use Yew\Core\Order\Order;
use Yew\Core\Server\Server;

abstract class AbstractPlugin extends Order implements PluginInterface
{
    /**
     * @var PluginInterfaceManager
     */
    protected $pluginInterfaceManager;

    /**
     * @var Channel
     */
    private $readyChannel;

    /**
     * AbstractPlugin constructor.
     */
    public function __construct()
    {
        $this->readyChannel = DIGet(Channel::class);

        Server::$instance->getContainer()->injectOn($this);
    }

    /**
     * Set to DI container
     *
     * @param $name
     * @param $value
     * @throws \Exception
     */
    public function setToDIContainer($name, $value)
    {
        DISet($name, $value);
    }

    /**
     * @inheritDoc
     * @return Channel
     */
    public function getReadyChannel(): Channel
    {
        return $this->readyChannel;
    }

    /**
     * Ready channel push message
     */
    public function ready()
    {
        $this->readyChannel->push("Ready");
    }

    /**
     * @inheritDoc
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        $this->pluginInterfaceManager = $pluginInterfaceManager;
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return void
     */
    public function init(Context $context)
    {
    }
}