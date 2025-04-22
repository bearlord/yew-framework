<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugin;

use Exception;
use Throwable;
use Yew\Core\Channel\Channel;
use Yew\Core\Context\Context;
use Yew\Core\Order\OrderOwnerTrait;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Server;

class PluginInterfaceManager implements PluginInterface
{
    use OrderOwnerTrait;
    use GetLogger;

    /**
     * @var Server
     */
    private Server $server;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var Channel
     */
    private $readyChannel;

    /**
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
        try {
            $this->readyChannel = DIGet(Channel::class);
            $this->eventDispatcher = DIGet(EventDispatcher::class);
        } catch (Throwable $throwable) {
            //do nothing
        }
    }

    /**
     * Add plug
     *
     * @param AbstractPlugin $plugin
     */
    public function addPlugin(AbstractPlugin $plugin)
    {
        $this->addOrder($plugin);
        $plugin->onAdded($this);
    }

    /**
     * Get plug
     *
     * @param String $className
     * @return PluginInterface|null
     */
    public function getPlug(String $className): ?PluginInterface
    {
        $plugin = $this->orderClassList[$className] ?? null;
        if ($plugin instanceof PluginInterface) {
            return $plugin;
        } else {
            return null;
        }
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return void
     * @throws Exception
     */
    public function init(Context $context)
    {
        foreach ($this->orderList as $plugin) {
            if ($plugin instanceof PluginInterface) {
                $plugin->init($context);
            }
        }
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        //Dispatch PlugManagerEvent: PlugBeforeServerStartEvent event
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugBeforeServerStartEvent, $this));
        }

        foreach ($this->orderList as $plugin) {
            if ($plugin instanceof PluginInterface) {
                $plugin->beforeServerStart($context);
            }
        }

        //Dispatch PlugManagerEvent:PlugAfterServerStartEvent event
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugAfterServerStartEvent, $this));
        }
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return void
     * @throws Exception
     */
    public function beforeProcessStart(Context $context)
    {
        //Dispatch PlugManagerEvent:PlugBeforeProcessStartEvent event
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugBeforeProcessStartEvent, $this));
        }

        foreach ($this->orderList as $plugin) {
            if ($plugin instanceof PluginInterface) {
                try {
                    $plugin->beforeProcessStart($context);
                } catch (Throwable $e) {
                    $this->error($e);
                    $this->error(sprintf("%s plugin failed to load", $plugin->getName()));
                    continue;
                }
                if (!$plugin->getReadyChannel()->pop(5)) {
                    $plugin->getReadyChannel()->close();
                    $this->error(sprintf("%s plugin failed to load", $plugin->getName()));
                    if ($this->eventDispatcher != null) {
                        $this->eventDispatcher->dispatchEvent(new PluginEvent(PluginEvent::PlugFailEvent, $plugin));
                    }
                } else {
                    if ($this->eventDispatcher != null) {
                        $this->eventDispatcher->dispatchEvent(new PluginEvent(PluginEvent::PluginSuccessEvent, $plugin));
                    }
                }
            }
        }

        //Dispatch PlugManagerEvent:PlugAfterProcessStartEvent event
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugAfterProcessStartEvent, $this));
        }
        $this->readyChannel->push("Ready");
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "PlugManager";
    }

    /**
     * @return Channel
     */
    public function getReadyChannel(): Channel
    {
        return $this->readyChannel;
    }

    /**
     * Wait to ready
     */
    public function waitReady()
    {
        $this->readyChannel->pop();
        $this->readyChannel->close();

        //Dispatch PlugManagerEvent:PlugAllReadyEvent event
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugAllReadyEvent, $this));
        }
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }
}
