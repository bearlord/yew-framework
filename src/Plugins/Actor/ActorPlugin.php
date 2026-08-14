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
use Yew\Cluster\ClusterConfig;
use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\IpcShardRouter;
use Yew\Cluster\Transport\RemoteTransport;
use Yew\Cluster\Transport\RemoteEnvelope;
use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorIpcProxy;
use Yew\Plugins\Actor\ActorManager;
use Yew\Plugins\Actor\Persistence\ActorStore;
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

            if ($router instanceof ShardRouter && $transport instanceof RemoteTransport) {
                // The shard router is purely cluster-aware (node resolution). Local
                // actor resolution is handled by the actor layer itself
                // (ActorManager::getActor fast path), so no locator injection is
                // needed — the dependency stays strictly actor -> cluster.
                $this->actorManager->setShardRouter($router);
                $this->actorManager->setRemoteTransport($transport);

                // The transport is actor-agnostic; it delegates every inbound
                // (cross-node) request to this handler, which lives in the actor
                // layer.
                if (method_exists($transport, 'setInboundHandler')) {
                    $transport->setInboundHandler([$this, 'handleRemoteEnvelope']);
                }

                // Topology rebalance: evict local actors that no longer belong to
                // this node after a ring change. The router (IpcShardRouter) is
                // refreshed on a worker timer against the authoritative view from
                // the cluster-state process, so this fires on real membership
                // changes. Actor placement itself is resolved via IPC in locate().
                $router->onRebalance(function (array $changed, ShardRouter $r) {
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
                        Server::$instance->getLog()->info("cluster: evicted local actor [$name] (now owned by [$owner])");
                    }
                    if ($changed !== []) {
                        $log = Server::$instance->getLog();
                        $log->warning(
                            "cluster: ring changed for nodes [" . implode(',', $changed) . "]; "
                            . "evicted mis-owned local actors above"
                        );
                        // Failover of persisted actors: when a peer node goes down,
                        // its replicated actor copies are buffered in every other
                        // node's cluster-state process. When such an actor is (re)created
                        // locally its recovery() reads fall back to that replica via IPC
                        // (ClusterActorStore::findReplica -> IpcReplicaTransport). List the
                        // resurrect-able actors here so the (re)creation path can pick them
                        // up instead of starting empty.
                        if ($r instanceof IpcShardRouter) {
                            foreach ($changed as $nodeId) {
                                $candidates = $r->clusterFailoverActors($nodeId);
                                if ($candidates !== []) {
                                    $log->info(
                                        "cluster: peer [$nodeId] down; actors available for "
                                        . "failover recovery: " . implode(',', $candidates)
                                    );
                                }
                            }
                        }
                    }
                });
            }
        }
        return;
    }

    /**
     * Cross-node supervision (failover of persisted actors after a peer node
     * dies) was previously driven by the legacy multi-worker GossipClusterState
     * engine (onNodeDown + ClusterActorStore replication). In Stage 2 the cluster
     * authority moved into the single "cluster-state" helper process, so that
     * engine no longer exists in the worker. Re-pointing failover at the new
     * architecture (node-down detection in the cluster-state process + a
     * replicated store resolved via IPC) is a separate follow-up stage and is
     * intentionally left unwired here. Topology-driven eviction of mis-owned
     * local actors still happens via IpcShardRouter::onRebalance().
     */

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

        // Cross-node actor creation: the requesting node hashed this actor name
        // onto us (the owner node), so we materialise it in our own actor worker
        // pool and report success/failure back over the wire. The result must be
        // serialisable (no IPC proxy object crosses the TCP boundary).
        if ($env->kind === RemoteEnvelope::KIND_CREATE) {
            try {
                $result = ActorSystem::create(
                    $env->className,
                    $env->actorName,
                    $env->actorData,
                    true,
                    5,
                    $env->parent
                );
                if ($result === false) {
                    return ['code' => 500, 'message' => 'actor create timeout'];
                }
                return ['code' => 200, 'message' => 'created', 'data' => ['actorName' => $env->actorName]];
            } catch (\Throwable $e) {
                return ['code' => 500, 'message' => $e->getMessage()];
            }
        }

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