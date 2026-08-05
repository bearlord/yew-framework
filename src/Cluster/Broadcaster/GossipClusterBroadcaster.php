<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Broadcaster;

use Yew\Core\Plugins\Logger\GetLogger;

/**
 * ClusterBroadcaster backed by GossipClusterState membership + an independent
 * GossipTransport (UDP) channel dedicated to multicast payloads.
 *
 * It does NOT reuse the cluster's internal gossip transport, so multicast
 * traffic can never be mistaken for a SYNC/SYN-ACK gossip frame.
 */
class GossipClusterBroadcaster implements ClusterBroadcaster
{
    use GetLogger;

    /**
     * @var GossipClusterState
     */
    protected GossipClusterState $state;

    /**
     * @var GossipTransport
     */
    protected GossipTransport $transport;

    public function __construct(GossipClusterState $state, GossipTransport $transport)
    {
        $this->state = $state;
        $this->transport = $transport;
    }

    /**
     * @inheritDoc
     */
    public function broadcast(string $channel, string $message): void
    {
        $local = $this->state->getLocalNodeId();

        foreach ($this->state->aliveNodes() as $id => $member) {
            if ($id === $local) {
                continue;
            }
            $peer = $member->host . ':' . $member->port;
            $payload = json_encode([
                'type' => 'mc',
                'channel' => $channel,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE);

            try {
                $this->transport->sendTo($peer, $payload);
            } catch (\Throwable $e) {
                $this->error(sprintf('[GossipClusterBroadcaster] send to %s failed: %s', $peer, $e->getMessage()));
            }
        }
    }

    /**
     * Parse an incoming wire payload back into [channel, message].
     *
     * Returns null for anything that is not a multicast frame (so the caller
     * can safely ignore foreign packets on the shared socket).
     *
     * @param string $payload
     * @return array{channel:string,message:string}|null
     */
    public static function parse(string $payload): ?array
    {
        $data = json_decode($payload, true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'mc') {
            return null;
        }
        if (!isset($data['channel'], $data['message'])) {
            return null;
        }
        return ['channel' => (string) $data['channel'], 'message' => (string) $data['message']];
    }
}
