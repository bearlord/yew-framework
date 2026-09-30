<?php
/**
 * Yew framework - Connection plugin
 *
 * Registers a dedicated helper process that stores connection-level routing
 * state (fd <-> uid, clientId <-> uid, clientId <-> session_start) so the data
 * survives worker restarts, unlike the static properties on Server.
 */

namespace Yew\Plugins\Mqtt\Connection;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Mqtt\Connection\MqttConnection;
use Yew\Plugins\Mqtt\Connection\MqttConnectionConfig;
use Yew\Plugins\Mqtt\Connection\MqttConnectionProcess;
use Yew\Plugins\Mqtt\Topic\LocalDeliveryGateway;
use Yew\Plugins\Mqtt\Topic\MqttClusterBroadcaster;
use Yew\Plugins\Mqtt\Topic\MqttTopic;
use Yew\Plugins\Mqtt\Topic\Storage\DriverInterface;
use Yew\Plugins\Mqtt\Topic\Storage\StorageFactory;
use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\Transport\GossipTransport;
use Yew\Cluster\Transport\UdpGossipTransport;

class MqttConnectionPlugin extends AbstractPlugin
{
    use GetLogger;

    const PROCESS_GROUP_NAME = "HelperGroup";

    /**
     * @var MqttConnectionConfig
     */
    private MqttConnectionConfig $mqttConnectionConfig;

    public function __construct()
    {
        parent::__construct();

        $this->initConfig();
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Connection";
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        $this->mqttConnectionConfig->merge();

        Server::$instance->addProcess(
            $this->mqttConnectionConfig->getProcessName(),
            MqttConnectionProcess::class,
            self::PROCESS_GROUP_NAME
        );
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName()
            == $this->mqttConnectionConfig->getProcessName()
        ) {
            $mqttConnection = new MqttConnection();
            $mqttTopic = new MqttTopic(
                new LocalDeliveryGateway($mqttConnection),
                $this->createTopicDriver()
            );

            $this->wireClusterBroadcaster($mqttTopic);

            $this->setToDIContainer(MqttConnection::class, $mqttConnection);
            $this->setToDIContainer(MqttTopic::class, $mqttTopic);
        }

        $this->ready();
    }

    /**
     * Build the optional MQTT subscription persistence driver from the
     * "yew.topic.storage" config (reused from the Topic plugin). Returns null
     * when no storage driver is configured, in which case subscriptions are
     * kept in memory only.
     *
     * @return DriverInterface|null
     */
    private function createTopicDriver(): ?DriverInterface
    {
        $config = Server::$instance->getConfigContext()->get("yew.mqtt-topic");
        $storage = $config["storage"] ?? null;

        if (empty($storage)) {
            return null;
        }

        return StorageFactory::create($storage);
    }

    /**
     * Optionally wire a cluster broadcaster into MqttTopic and start the inbound
     * receive loop, so publishes are fanned out to (and received from) other
     * cluster nodes. No-op unless cluster mode is enabled and a dedicated
     * mqtt.clusterPort is configured.
     *
     * @param MqttTopic $mqttTopic
     * @return void
     */
    protected function wireClusterBroadcaster(MqttTopic $mqttTopic): void
    {
        if (!$this->mqttConnectionConfig->isClusterEnabled()) {
            return;
        }
        $port = $this->mqttConnectionConfig->getClusterPort();
        if ($port <= 0) {
            $this->warn('MQTT cluster enabled but mqtt.clusterPort not set (>0); cluster fan-out disabled');
            return;
        }
        if (!class_exists(GossipClusterState::class)) {
            $this->warn('MQTT cluster enabled but GossipClusterState unavailable; cluster fan-out disabled');
            return;
        }
        try {
            /** @var GossipClusterState $state */
            $state = DIGet(GossipClusterState::class);
        } catch (\Throwable $e) {
            $this->warn('MQTT cluster enabled but GossipClusterState not in DI: ' . $e->getMessage());
            return;
        }

        // Dedicated UDP transport for MQTT fan-out traffic (separate from the
        // cluster's internal gossip channel to avoid frame collisions).
        $transport = new UdpGossipTransport('0.0.0.0', $port, '127.0.0.1:' . $port);
        $broadcaster = new MqttClusterBroadcaster($state, $transport, $port);
        $mqttTopic->setBroadcaster($broadcaster);

        $this->startClusterReceiveLoop($mqttTopic, $transport);
    }

    /**
     * Start the UDP receive loop inside the mqtt-connection process. Incoming
     * MQTT fan-out frames are decoded and delivered to local subscribers only
     * (via publishLocal, which does not re-broadcast), preventing loops.
     *
     * @param MqttTopic $mqttTopic
     * @param GossipTransport $transport
     * @return void
     */
    protected function startClusterReceiveLoop(MqttTopic $mqttTopic, GossipTransport $transport): void
    {
        try {
            $transport->start();
        } catch (\Throwable $e) {
            $this->error('[MqttConnection] failed to start cluster transport: ' . $e->getMessage());
            return;
        }

        go(function () use ($mqttTopic, $transport) {
            while (true) {
                try {
                    $payload = $transport->receive(0.5);
                } catch (\Throwable $e) {
                    break;
                }
                if ($payload === null) {
                    continue;
                }
                $frame = MqttClusterBroadcaster::parse($payload);
                if ($frame === null) {
                    continue;
                }
                if ($frame['channel'] !== MqttTopic::CLUSTER_CHANNEL) {
                    continue;
                }
                $message = json_decode($frame['message'], true);
                if (!is_array($message) || !isset($message['topic'])) {
                    continue;
                }
                $mqttTopic->publishLocal(
                    (string)$message['topic'],
                    $message['data'] ?? null,
                    isset($message['exclude']) && is_array($message['exclude']) ? $message['exclude'] : null
                );
            }
        });
    }

    /**
     * Init mqttConnectionConfig
     * @return void
     */
    protected function initConfig()
    {
        $this->mqttConnectionConfig = new MqttConnectionConfig();
    }
}
