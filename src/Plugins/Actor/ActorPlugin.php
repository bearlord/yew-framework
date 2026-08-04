<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Ipc\IpcPlugin;
use Yew\Plugins\Actor\Cluster\ClusterNode;
use Yew\Plugins\Actor\Cluster\GossipClusterState;
use Yew\Plugins\Actor\Cluster\NodeKey;
use Yew\Plugins\Actor\Cluster\UdpGossipTransport;
use Yew\Plugins\Actor\Cluster\GossipShardRouter;
use Yew\Plugins\Actor\Cluster\PooledTcpRemoteTransport;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use Yew\Core\Log\Log;

class ActorPlugin extends AbstractPlugin
{

    /**
     * @var ActorConfig|null
     */
    private ?ActorConfig $actorConfig;

    /**
     * @var ActorManager
     */
    protected ActorManager $actorManager;

    public function __construct()
    {
        parent::__construct();

        $this->initConfig();

        $this->atAfter(IpcPlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new IpcPlugin());
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Actor";
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        $this->actorConfig->merge();
        for ($i = 0; $i < $this->actorConfig->getWorkerCount(); $i++) {
            Server::$instance->addProcess("actor-$i", ActorProcess::class, ActorConfig::GROUP_NAME);
        }
        
        $this->actorManager = ActorManager::getInstance();

        // Cluster seam: when enabled, swap the local (single-machine) shard
        // router for the gossip-backed one and start membership heartbeats.
        if ($this->actorConfig->isClusterEnabled()) {
            $this->startCluster();
        }
        return;
    }

    /**
     * Wire up the gossip membership service + consistent-hash shard router and
     * run the failure-detection timer. Membership is now discovered over UDP
     * gossip across physical machines; actor calls cross nodes over a pooled
     * TCP transport.
     */
    private function startCluster(): void
    {
        $cfg = $this->actorConfig;
        $state = new GossipClusterState(
            $cfg->getClusterNodeId(),
            $cfg->getClusterSuspectAfter(),
            $cfg->getClusterDownAfter()
        );
        // Prefer per-node asymmetric keys; fall back to the shared HMAC secret.
        $priv = $cfg->getClusterPrivateKey();
        $pub = $cfg->getClusterPublicKey();
        if ($priv !== '' && $pub !== '') {
            $key = NodeKey::fromPem($priv, $pub);
            $state->setKey($key, $cfg->getClusterTrustStore(), $cfg->getClusterClockSkew() * 120);
        } else {
            $state->setSecret($cfg->getClusterSecret(), $cfg->getClusterClockSkew());
        }
        $state->join($cfg->getClusterHost(), $cfg->getClusterPort(), $cfg->getClusterWeight());

        // Cross-node durable store: when cluster + persistence + storeEnabled are
        // all on, wrap the local FileActorStore in a cluster-aware one that
        // replicates every actor's events/snapshots to peer nodes. Actors then
        // inject this store via DI and transparently gain cross-node durability.
        $clusterStore = null;
        if ($cfg->isClusterEnabled() && $cfg->isPersistenceEnabled() && $cfg->isClusterStoreEnabled()) {
            $clusterStore = new ClusterActorStore(
                new FileActorStore($cfg->getPersistenceDir()),
                $cfg->getClusterReplicationFactor()
            );
            $clusterStore->setCluster($state);
            $state->setActorStore($clusterStore);
            // Register as a container singleton so Actor::injectedStore is DI-injected.
            DISet(ClusterActorStore::class, $clusterStore);
        }

        // Real UDP gossip wire layer.
        $udp = new UdpGossipTransport(
            $cfg->getClusterGossipHost(),
            $cfg->getClusterGossipPort() ?: ($cfg->getClusterPort() + 1000),
            $cfg->getClusterGossipBroadcast()
        );
        $state->start($udp, $cfg->getClusterSeeds());

        $localNode = new ClusterNode(
            $cfg->getClusterNodeId(),
            $cfg->getClusterHost(),
            $cfg->getClusterPort(),
            true
        );
        $router = new GossipShardRouter($state, $localNode, $cfg->getRoutingReplicas());
        $this->actorManager->setShardRouter($router);

        // Cross-node supervision: when a peer node goes DOWN, fail over the
        // persisted actors that now hash to this node (recovered from replicas).
        if ($clusterStore !== null) {
            $state->onNodeDown(function (string $deadNodeId) use ($router, $state) {
                $this->failoverFrom($deadNodeId, $router, $state);
            });
        }

        // Topology rebalance: react to ring changes. The router invokes this hook
        // as onRebalance(array $changedNodeIds, GossipShardRouter $router).
        // When this node LOSES a shard, evict the local actor instances that no
        // longer belong here (they will be served by their new owner via
        // ActorIpcProxy). GAINED shards are logged; cross-node migration of
        // non-persisted actors is not yet wired.
        $router->onRebalance(function (array $changed, GossipShardRouter $router) {
            $localNodeId = $router->getLocalNode()->getNodeId();
            foreach ($this->actorManager->getLocalActorNames() as $name) {
                $actor = $this->actorManager->getActor($name);
                if (!$actor instanceof Actor) {
                    continue; // remote proxy or not local
                }
                $owner = $router->ownerOf($name);
                if ($owner === $localNodeId || $owner === null) {
                    continue; // still ours or unrouted
                }
                $this->actorManager->removeActor($actor);
                Log::info("cluster: evicted local actor [$name] (now owned by [$owner])");
            }
            if ($changed !== []) {
                Log::warning(
                    "cluster: ring changed for nodes [" . implode(',', $changed) . "]; "
                    . "cross-node migration of non-persisted actors is not wired yet"
                );
            }
        });

        // Pooled TCP transport for cross-node actor calls.
        $transport = new PooledTcpRemoteTransport(
            $cfg->getClusterHost(),
            $cfg->getClusterPort(),
            $cfg->getClusterNodeId(),
            $cfg->getClusterPoolSize()
        );
        $transport->start();
        $this->actorManager->setRemoteTransport($transport);

        // Keep a handle so external code (debug endpoints) can inspect it.
        DISet(GossipClusterState::class, $state);

        // Failure-detection + gossip ticker.
        \Swoole\Timer::tick((int) ($cfg->getClusterHeartbeatInterval() * 1000), function () use ($state) {
            $state->tick();
        });
    }

    /**
     * User-registered cross-node supervision handler. Invoked for each persisted
     * actor that should be resurrected on this node after its owning node died.
     * The handler is responsible for re-creating the actor (the ClusterActorStore
     * injected into it will recover its state from the replicated event log).
     *
     * @var callable(string):void|null
     */
    private $failoverHandler = null;

    /**
     * Register a handler called when a peer node fails and one of its persisted
     * actors now hashes to this node. Signature: (string $actorName) => void.
     *
     * @param callable(string):void $cb
     * @return void
     */
    public function onNodeDown(callable $cb): void
    {
        $this->failoverHandler = $cb;
    }

    /**
     * Cross-node supervision: a peer node died. For every actor whose replicated
     * store this node holds and that now hashes to this node (per the consistent
     * hash ring), ask the registered handler to resurrect it. The actor is rebuilt
     * from the replicated event log, so no state is lost across the failure.
     */
    private function failoverFrom(
        string $deadNodeId,
        GossipShardRouter $router,
        GossipClusterState $state
    ): void {
        if ($this->failoverHandler === null) {
            return;
        }
        $localNodeId = $state->getLocalNodeId();
        foreach ($state->getReplicatedActorNames($deadNodeId) as $actorName) {
            // Only resurrect actors that the ring now maps to this node, and that
            // are not already alive here.
            if ($router->getNode($actorName) !== $localNodeId) {
                continue;
            }
            if (ActorManager::getInstance()->getActorRaw($actorName) !== null) {
                continue;
            }
            ($this->failoverHandler)($actorName);
        }
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();

        // Optional lifecycle-hook smoke test. Off by default; enable with:
        //   YEW_RUN_LIFECYCLE_SMOKE=1 php server.php
        // Runs once inside the actor-0 process after startup.
        if (getenv('YEW_RUN_LIFECYCLE_SMOKE') !== false
            && Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() === "actor-0"
        ) {
            \Swoole\Coroutine::create(function () {
                $candidates = [];
                if (defined('ROOT_DIR')) {
                    $candidates[] = ROOT_DIR . 'test/Actor/LifecycleHookSmoke.php';
                }
                $candidates[] = (getcwd() ?: __DIR__) . '/test/Actor/LifecycleHookSmoke.php';
                foreach ($candidates as $file) {
                    // cygwin shells expose /cygdrive/d/... paths that the native
                    // swoole-cli binary cannot stat; map them back to Windows form.
                    if (str_starts_with($file, '/cygdrive/')) {
                        $file = preg_replace('#^/cygdrive/([a-z])/#i', '$1:/', $file);
                    }
                    if (is_file($file)) {
                        require_once $file;
                        \App\Test\runLifecycleHookSmoke();
                        return;
                    }
                }
            });
        }
    }

	/**
	 * @return void
	 */
	protected function initConfig()
	{
		$config = Server::$instance->getConfigContext()->get("yew.actor") ?? [];

		$actorConfig = new ActorConfig();

		$actorConfig->setMaxCount((int) ($config["maxCount"] ?? 10000));
		$actorConfig->setWorkerCount((int) ($config["workerCount"] ?? 1));
		$actorConfig->setMaxClassCount((int) ($config["maxClassCount"] ?? 100));
		$actorConfig->setMailboxCapacity((int) ($config["mailboxCapacity"] ?? 100));
		$actorConfig->setMailboxOverflow((string) ($config["mailboxOverflow"] ?? "block"));
		$actorConfig->setMailboxPushTimeout((float) ($config["mailboxPushTimeout"] ?? 1.0));
		$actorConfig->setSupervisorStrategy((string) ($config["supervisorStrategy"] ?? "restart"));
		$actorConfig->setSupervisorMaxRetries((int) ($config["supervisorMaxRetries"] ?? 3));
		$actorConfig->setSupervisorMode((string) ($config["supervisorMode"] ?? "one-for-one"));
		$actorConfig->setPersistenceEnabled((bool) ($config["persistenceEnabled"] ?? false));
		$actorConfig->setPersistenceDir((string) ($config["persistenceDir"] ?? "/tmp/yew-actor-store"));
		$actorConfig->setRoutingStrategy((string) ($config["routingStrategy"] ?? "round-robin"));
		$actorConfig->setRoutingReplicas((int) ($config["routingReplicas"] ?? 128));
		$actorConfig->setDispatcher((string) ($config["dispatcher"] ?? "coroutine"));
		$actorConfig->setDispatcherPoolSize((int) ($config["dispatcherPoolSize"] ?? 4));
		$actorConfig->setTelemetryEnabled((bool) ($config["telemetryEnabled"] ?? false));
		$actorConfig->setClusterEnabled((bool) ($config["cluster"]["enabled"] ?? false));
		$actorConfig->setClusterNodeId((string) ($config["cluster"]["nodeId"] ?? ('node-' . gethostname())));
		$actorConfig->setClusterHost((string) ($config["cluster"]["host"] ?? "127.0.0.1"));
		$actorConfig->setClusterPort((int) ($config["cluster"]["port"] ?? 0));
		$actorConfig->setClusterWeight((int) ($config["cluster"]["weight"] ?? 1));
		$actorConfig->setClusterSuspectAfter((int) ($config["cluster"]["suspectAfter"] ?? 3));
		$actorConfig->setClusterDownAfter((int) ($config["cluster"]["downAfter"] ?? 8));
		$actorConfig->setClusterHeartbeatInterval((float) ($config["cluster"]["heartbeatInterval"] ?? 1.0));
		$actorConfig->setClusterGossipHost((string) ($config["cluster"]["gossipHost"] ?? "0.0.0.0"));
		$actorConfig->setClusterGossipPort((int) ($config["cluster"]["gossipPort"] ?? 0));
		$actorConfig->setClusterGossipBroadcast((string) ($config["cluster"]["gossipBroadcast"] ?? "239.0.0.1:49999"));
		$seeds = $config["cluster"]["seeds"] ?? [];
		$actorConfig->setClusterSeeds(is_array($seeds) ? array_values(array_map('strval', $seeds)) : []);
		$actorConfig->setClusterPoolSize((int) ($config["cluster"]["poolSize"] ?? 16));
		$actorConfig->setClusterSecret((string) ($config["cluster"]["secret"] ?? ''));
		$actorConfig->setClusterClockSkew((int) ($config["cluster"]["clockSkew"] ?? 30));
		$actorConfig->setClusterStoreEnabled((bool) ($config["cluster"]["storeEnabled"] ?? false));
		$actorConfig->setClusterReplicationFactor((int) ($config["cluster"]["replicationFactor"] ?? 2));
		\Yew\Plugins\Actor\Telemetry\ActorTelemetry::enable($actorConfig->isTelemetryEnabled());

		$this->actorConfig = $actorConfig;
		// Register as a container singleton so Actor::injectOn() and
		// ActorManager::DIGet(ActorConfig::class) resolve the configured instance
		// instead of leaving Actor::$actorConfig uninitialized (typed property).
		DISet(ActorConfig::class, $actorConfig);
	}
}