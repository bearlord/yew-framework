<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Multicast;

use Yew\Core\Context\Context;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Plugins\Actor\ActorConfig;
use Yew\Plugins\Actor\ActorPlugin;
use Yew\Plugins\Aop\AopConfig;
use Yew\Coroutine\Server\Server;

class MulticastPlugin extends AbstractPlugin
{
    /**
     * @var string
     */
    const PROCESS_GROUP_NAME = "HelperGroup";

    /**
     * @var Table
     */
    protected $channelTable;

    /**
     * @var MulticastConfig
     */
    protected $multicastConfig;

    protected $channel;

    /**
     * Register the multicast plugin, after ActorPlugin.
     *
     * @param MulticastConfig|null $multicastConfig Optional explicit config
     */
    public function __construct(?MulticastConfig $multicastConfig = null)
    {
        parent::__construct();
        
        if ($multicastConfig == null) {
            $multicastConfig = new MulticastConfig();
        }

        $this->multicastConfig = $multicastConfig;
        $this->atAfter(ActorPlugin::class);
    }

    /**
     * Plugin init hook (no-op beyond parent); kept for lifecycle parity.
     *
     * @param Context $context Framework context
     */
    public function init(Context $context)
    {
        parent::init($context);
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Multicast";
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return mixed
     * @throws \ReflectionException
     */
    public function beforeServerStart(Context $context)
    {
        $this->multicastConfig->merge();

        $this->channelTable = new Table($this->multicastConfig->getCacheChannelCount());
        $this->channelTable->column("channel", Table::TYPE_STRING, $this->multicastConfig->getChannelMaxLength());
        $this->channelTable->column("actor", Table::TYPE_STRING, $this->multicastConfig->getActorMaxLength());
        $this->channelTable->create();

        Server::$instance->addProcess($this->multicastConfig->getProcessName(), MulticastProcess::class, self::PROCESS_GROUP_NAME);
        return;
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return mixed
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() == $this->multicastConfig->getProcessName()) {
            $this->channel = new Channel($this->channelTable);
            $this->setToDIContainer(Channel::class, $this->channel);
        }
        
        $this->ready();
    }
}