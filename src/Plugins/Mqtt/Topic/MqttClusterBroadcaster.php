<?php
/**
 * Yew framework - MQTT cluster fan-out broadcaster.
 *
 * Sends a topic publish to every other alive node on a dedicated UDP port
 * (independent of the cluster's internal gossip port), and reuses the same
 * wire format ({"type":"mc",...}) as GossipClusterBroadcaster so the receiving
 * mqtt-connection process can recognise the frame and keep it apart from the
 * cluster's internal gossip traffic.
 */

namespace Yew\Plugins\Mqtt\Topic;

use Yew\Cluster\Broadcaster\ClusterBroadcaster;
use Yew\Cluster\Broadcaster\GossipClusterBroadcaster;
use Yew\Cluster\State\ClusterStateInterface;
use Yew\Cluster\Transport\GossipTransport;
use Yew\Core\Plugins\Logger\GetLogger;

class MqttClusterBroadcaster implements ClusterBroadcaster
{
    use GetLogger;

    /**
     * @var ClusterStateInterface
     */
    private ClusterStateInterface $state;

    /**
     * @var GossipTransport
     */
    private GossipTransport $transport;

    /**
     * Dedicated UDP port every node listens on for MQTT fan-out frames.
     * @var int
     */
    private int $port;

    public function __construct(ClusterStateInterface $state, GossipTransport $transport, int $port)
    {
        $this->state = $state;
        $this->transport = $transport;
        $this->port = $port;
    }

    /**
     * @inheritDoc
     */
    public function broadcast(string $channel, string $message): void
    {
        $local = $this->state->getLocalNodeId();

        $payload = json_encode([
            'type' => 'mc',
            'channel' => $channel,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($this->state->aliveNodes() as $id => $member) {
            if ($id === $local) {
                continue;
            }
            $peer = $member->host . ':' . $this->port;
            try {
                $this->transport->sendTo($peer, $payload);
            } catch (\Throwable $e) {
                $this->error(sprintf('[MqttClusterBroadcaster] send to %s failed: %s', $peer, $e->getMessage()));
            }
        }
    }

    /**
     * Parse an incoming wire payload back into [channel, message], or null for
     * anything that is not an MQTT fan-out frame.
     *
     * @param string $payload
     * @return array{channel:string,message:string}|null
     */
    public static function parse(string $payload): ?array
    {
        return GossipClusterBroadcaster::parse($payload);
    }
}
