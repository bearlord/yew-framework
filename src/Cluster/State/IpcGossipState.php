<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

use Yew\Cluster\GetClusterState;

/**
 * Minimal {@see ClusterStateInterface} adapter used by the multicast broadcaster
 * (which runs in the multicast helper process, NOT the cluster-state process).
 *
 * It carries no wire of its own: membership is fetched on demand from the
 * authoritative cluster-state process over IPC (see {@see GetClusterState}). This
 * replaces the legacy worker-side GossipClusterState for the multicast fan-out
 * path, so multicast no longer depends on the old shared-table gossip.
 */
class IpcGossipState implements ClusterStateInterface
{
    use GetClusterState;

    private string $localNodeId;
    private ?array $view = null;

    public function __construct(string $localNodeId)
    {
        $this->localNodeId = $localNodeId;
    }

    private function view(): array
    {
        if ($this->view === null) {
            $this->view = $this->getClusterView();
        }
        return $this->view;
    }

    public function getLocalNodeId(): string
    {
        return $this->localNodeId;
    }

    /**
     * @return ClusterMember[]
     */
    public function aliveNodes(): array
    {
        $out = [];
        foreach ($this->view() as $row) {
            if ($row['status'] === ClusterMember::STATUS_UP) {
                $out[$row['nodeId']] = new ClusterMember(
                    $row['nodeId'], $row['host'], $row['port'], $row['local'],
                    ClusterMember::STATUS_UP, $row['lastHeartbeat']
                );
            }
        }
        return $out;
    }

    public function getNode(string $nodeId): ?ClusterMember
    {
        $row = $this->view()[$nodeId] ?? null;
        if ($row === null) {
            return null;
        }
        return new ClusterMember(
            $row['nodeId'], $row['host'], $row['port'], $row['local'],
            $row['status'] === ClusterMember::STATUS_UP ? ClusterMember::STATUS_UP : ClusterMember::STATUS_DOWN,
            $row['lastHeartbeat']
        );
    }

    public function isLocal(string $nodeId): bool
    {
        return $nodeId === $this->localNodeId;
    }

    public function registerListener(callable $cb): void
    {
        // The multicast helper process does not act on membership changes;
        // it only reads the current view. No-op.
    }
}
