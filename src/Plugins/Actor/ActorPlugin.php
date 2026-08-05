<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Log\Log;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Ipc\IpcPlugin;
use Yew\Cluster\ClusterConfig;
use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\GossipShardRouter;
use Yew\Cluster\Transport\RemoteTransport;
use Yew\Cluster\Transport\RemoteEnvelope;
use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorIpcProxy;
use Yew\Plugins\Actor\ActorManager;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use Yew\Plugins\Actor\Telemetry\Tracer;

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
        // Cluster seam: ClusterPlugin (configured from the top-level "yew.cluster"
        // subtree) already published the cluster primitives through the container.
        // Because ClusterPlugin's beforeServerStart runs before this one (see
        // Application), we can now pull ShardRouter / RemoteTransport /
        // GossipClusterState and wire them into the actor runtime. This keeps the
        // dependency direction strictly actor -> cluster.
        $clusterConfig = DIGet(ClusterConfig::class);

        // Mirror cluster.* onto ActorConfig for legacy call-sites that read via
        // ActorConfig getters. MUST happen before merge(), otherwise the mirrored
        // values are not part of the merged/published config.
        if ($clusterConfig instanceof ClusterConfig) {
            $this->actorConfig->applyClusterCompat($clusterConfig);
        }

        $this->actorConfig->merge();
        for ($i = 0; $i < $this->actorConfig->getWorkerCount(); $i++) {
            Server::$instance->addProcess("actor-$i", ActorProcess::class, ActorConfig::GROUP_NAME);
        }
        
        $this->actorManager = ActorManager::getInstance();

        if ($clusterConfig instanceof ClusterConfig && $clusterConfig->isEnabled()) {
            $router = DIGet(ShardRouter::class);
            $transport = DIGet(RemoteTransport::class);
            $state = DIGet(GossipClusterState::class);

            if ($router instanceof ShardRouter && $transport instanceof RemoteTransport) {
                // Local actor lookup is owned by the actor layer; inject it so the
                // router stays cluster-only.
                if (method_exists($router, 'setActorLocator')) {
                    $router->setActorLocator(function (string $name) {
                        return ActorManager::getInstance()->getActorRaw($name);
                    });
                }

                $this->actorManager->setShardRouter($router);
                $this->actorManager->setRemoteTransport($transport);

                // The transport is actor-agnostic; it delegates every inbound
                // (cross-node) request to this handler, which lives in the actor
                // layer.
                if (method_exists($transport, 'setInboundHandler')) {
                    $transport->setInboundHandler([$this, 'handleRemoteEnvelope']);
                }

                // Cluster-aware durable store: when persistence + replication are
                // both on, wrap the local FileActorStore so every actor's
                // events/snapshots are replicated to peer nodes.
                if ($this->actorConfig->isPersistenceEnabled()
                    && $clusterConfig->getReplicationFactor() > 0
                    && $state instanceof GossipClusterState) {
                    $store = new ClusterActorStore(
                        new FileActorStore($this->actorConfig->getPersistenceDir()),
                        $clusterConfig->getReplicationFactor()
                    );
                    $store->setCluster($state);
                    $state->setActorStore($store);
                    DISet(ClusterActorStore::class, $store);
                }

                if ($router instanceof GossipShardRouter) {
                    // Topology rebalance: evict local actors that no longer belong
                    // to this node after a ring change.
                    $router->onRebalance(function (array $changed, GossipShardRouter $r) {
                        $localNodeId = $r->getLocalNode()->getNodeId();
                        foreach ($this->actorManager->getLocalActorNames() as $name) {
                            $actor = $this->actorManager->getActor($name);
                            if (!$actor instanceof Actor) {
                                continue;
                            }
                            $owner = $r->ownerOf($name);
                            if ($owner === $localNodeId || $owner === null) {
                                continue;
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

                    // Cross-node supervision: resurrect persisted actors that now
                    // hash to this node after a peer node fails.
                    if ($state instanceof GossipClusterState) {
                        $state->onNodeDown(function (string $deadNodeId) use ($router, $state) {
                            $this->failoverFrom($deadNodeId, $router, $state);
                        });
                    }
                }
            }
        }
        return;
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
     * hash ring) and is not already alive here, invoke the user-registered
     * handler so it can resurrect the actor (rebuilt from the replicated event
     * log, so no state is lost across the failure).
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
            // ownerOf() maps an actor name onto the consistent-hash ring; only
            // actors that now hash to this node are resurrected here.
            if ($router->ownerOf($actorName) !== $localNodeId) {
                continue;
            }
            if (ActorManager::getInstance()->getActorRaw($actorName) !== null) {
                continue;
            }
            ($this->failoverHandler)($actorName);
        }
    }

    /**
     * Inbound cross-node request handler, injected into the (actor-agnostic)
     * RemoteTransport. Resolves the target actor locally and delivers the call
     * via the in-process IPC proxy, returning the ask result (or null for tell /
     * not-found) so the transport can frame the reply.
     *
     * @param RemoteEnvelope $env
     * @return mixed
     */
    public function handleRemoteEnvelope(RemoteEnvelope $env)
    {
        $manager = ActorManager::getInstance();
        $info = $manager->getActorInfo($env->actorName);
        if ($info === null) {
            return null;
        }

        if ($env->traceId !== null) {
            Tracer::continue($env->traceId);
        }

        $proxy = new ActorIpcProxy($env->actorName, true, 0);
        if ($env->kind === RemoteEnvelope::KIND_ASK) {
            try {
                return $proxy->ask($env->method, $env->arguments, 55);
            } catch (\Throwable $e) {
                return ['__error' => $e->getMessage()];
            }
        }

        $proxy->tell($env->method, $env->arguments);
        return null;
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

		// NOTE: cluster compatibility values are intentionally NOT read here.
		// This method runs from the constructor, i.e. during addPlugin(), so it
		// would depend on ClusterPlugin having been registered first (its
		// onAdded() is what publishes ClusterConfig). Relying on registration
		// order fails silently -- every cluster.* getter would quietly fall back
		// to its default. The mirroring is done in beforeServerStart() instead,
		// where the container is guaranteed to be populated.

		\Yew\Plugins\Actor\Telemetry\ActorTelemetry::enable($actorConfig->isTelemetryEnabled());

		$this->actorConfig = $actorConfig;
		// Register as a container singleton so Actor::injectOn() and
		// ActorManager::DIGet(ActorConfig::class) resolve the configured instance
		// instead of leaving Actor::$actorConfig uninitialized (typed property).
		DISet(ActorConfig::class, $actorConfig);
	}
}