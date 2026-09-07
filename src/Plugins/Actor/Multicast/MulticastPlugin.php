<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Multicast;

use Yew\Cluster\Broadcaster\GossipClusterBroadcaster;
use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\Transport\GossipTransport;
use Yew\Cluster\Transport\UdpGossipTransport;
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
     * @var GossipTransport|null Cluster-wide multicast transport (null when disabled).
     */
    protected ?GossipTransport $clusterTransport = null;

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

        $this->wireClusterBroadcaster();

        Server::$instance->addProcess($this->multicastConfig->getProcessName(), MulticastProcess::class, self::PROCESS_GROUP_NAME);
        return;
    }

    /**
     * Build the cluster broadcaster (when enabled) and inject it into MulticastConfig.
     *
     * The transport itself is NOT started here (master process does not run coroutines);
     * it is started lazily inside the multicast helper process via startClusterReceiveLoop().
     *
     * @return void
     */
    protected function wireClusterBroadcaster(): void
    {
        if (!$this->multicastConfig->isClusterEnabled()) {
            return;
        }
        if (!class_exists(GossipClusterState::class)) {
            $this->warn('Multicast cluster enabled but GossipClusterState unavailable; cluster fan-out disabled');
            return;
        }
        try {
            /** @var GossipClusterState $state */
            $state = DIGet(GossipClusterState::class);
        } catch (\Throwable $e) {
            $this->warn('Multicast cluster enabled but GossipClusterState not in DI: ' . $e->getMessage());
            return;
        }

        $port = $this->multicastConfig->getClusterPort();
        if ($port <= 0) {
            $this->warn('Multicast cluster enabled but multicast.clusterPort not set (>0); cluster fan-out disabled');
            return;
        }

        // Dedicated UDP transport for multicast traffic (kept separate from the
        // cluster's internal gossip channel to avoid frame collisions).
        $this->clusterTransport = new UdpGossipTransport('0.0.0.0', $port, '127.0.0.1:' . $port);
        $broadcaster = new GossipClusterBroadcaster($state, $this->clusterTransport);
        $this->multicastConfig->setBroadcaster($broadcaster);
    }

    /**
     * Start the cluster multicast transport and a receive loop inside the
     * multicast helper process. Incoming frames are re-published on the local
     * Channel so every in-process subscriber gets them (node-level fan-out).
     *
     * @return void
     */
    protected function startClusterReceiveLoop(): void
    {
        if ($this->clusterTransport === null) {
            return;
        }
        $transport = $this->clusterTransport;
        $channel = $this->channel;

        try {
            $transport->start();
        } catch (\Throwable $e) {
            $this->error('[Multicast] failed to start cluster transport: ' . $e->getMessage());
            return;
        }

        go(function () use ($transport, $channel) {
            while (true) {
                try {
                    $payload = $transport->receive(0.5);
                } catch (\Throwable $e) {
                    break;
                }
                if ($payload === null) {
                    continue;
                }
                $frame = GossipClusterBroadcaster::parse($payload);
                if ($frame === null) {
                    continue;
                }
                try {
                    $channel->publish($frame['channel'], $frame['message'], [], '');
                } catch (\Throwable $e) {
                    // Ignore publish errors from re-injected frames.
                }
            }
        });
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

            $this->startClusterReceiveLoop();
        }
        
        $this->ready();
    }
}