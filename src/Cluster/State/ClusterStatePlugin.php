<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\State;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Cluster\ClusterConfig;

/**
 * Registers a dedicated "cluster-state" helper process and publishes the
 * authoritative {@see ClusterState} into the DI container.
 *
 * Design (mirrors the Connection plugin):
 *   - EXACTLY ONE cluster-state process per node. It is the sole owner of the
 *     member table, the failure-detection tick and the consistent-hash ring, so
 *     there is no cross-worker disagreement (no split-brain) and no shared
 *     Swoole\Table is needed.
 *   - Worker processes (including actor workers) never touch ClusterState
 *     directly; they query it via the {@see \Yew\Cluster\GetClusterState} IPC
 *     proxy.
 *
 * Stage 1 seeds membership from configuration (local node + static peers) and
 * runs the in-process FD tick. The gossip UDP protocol is migrated into
 * ClusterState in a later stage; until then peers are trusted present and their
 * liveness is tracked by the tick against lastHeartbeat (fed by heartbeat()).
 */
class ClusterStatePlugin extends AbstractPlugin
{
    public const PROCESS_NAME = 'cluster-state';

    private ?ClusterConfig $clusterConfig = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function getName(): string
    {
        return "ClusterState";
    }

    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        try {
            $clusterConfig = DIGet(ClusterConfig::class);
        } catch (\Throwable $e) {
            $clusterConfig = new ClusterConfig();
            $clusterConfig->buildFromArray((array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []));
        }
        $this->clusterConfig = $clusterConfig;
    }

    public function beforeServerStart(Context $context)
    {
        if ($this->clusterConfig === null || !$this->clusterConfig->isEnabled()) {
            return;
        }
        // Register exactly one cluster-state helper process per node.
        Server::$instance->addProcess(
            self::PROCESS_NAME,
            ClusterStateProcess::class,
            self::PROCESS_NAME
        );
    }

    public function beforeProcessStart(Context $context)
    {
        if ($this->clusterConfig === null || !$this->clusterConfig->isEnabled()) {
            return;
        }
        // Only the cluster-state process owns the authoritative state and runs
        // the FD tick. Worker processes resolve it exclusively through IPC.
        $current = Server::$instance->getProcessManager()->getCurrentProcess();
        if ($current === null || $current->getProcessName() !== self::PROCESS_NAME) {
            return;
        }

        $cfg = $this->clusterConfig;
        $peers = $this->resolvePeers($cfg);

        $state = new ClusterState(
            $cfg->getNodeId(),
            $cfg->getSuspectAfter(),
            $cfg->getDownAfter(),
            $peers
        );
        $this->setToDIContainer(ClusterState::class, $state);

        $intervalMs = max(500, (int) ($cfg->getHeartbeatInterval() * 1000));
        \Swoole\Timer::tick($intervalMs, static function () use ($state) {
            $state->tick();
        });

        $this->ready();
    }

    /**
     * Resolve static peer endpoints from config. Prefers an explicit
     * `peers` map (nodeId => "host:port"); otherwise maps each seed to a
     * synthetic id. Stage 1 only seeds the view; gossip (stage 1.5) will
     * replace this with discovered membership.
     *
     * @param ClusterConfig $cfg
     * @return array<string,string>
     */
    private function resolvePeers(ClusterConfig $cfg): array
    {
        $raw = (array) (Server::$instance->getConfigContext()->get("yew.cluster.peers") ?? []);
        if ($raw !== []) {
            $peers = [];
            foreach ($raw as $id => $endpoint) {
                $peers[(string) $id] = (string) $endpoint;
            }
            return $peers;
        }
        // Fallback: seeds without explicit ids get synthetic ids.
        $peers = [];
        $i = 0;
        foreach ($cfg->getSeeds() as $seed) {
            $peers['peer-' . ($i++)] = (string) $seed;
        }
        return $peers;
    }
}
