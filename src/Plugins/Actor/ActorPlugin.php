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
use Yew\Plugins\Actor\Cluster\ClusterGossipUdpPort;
use Yew\Plugins\Actor\Cluster\ClusterTcpPort;
use Yew\Plugins\Actor\Cluster\GossipClusterState;
use Yew\Plugins\Actor\Cluster\NodeKey;
use Yew\Plugins\Actor\Cluster\UdpGossipTransport;
use Yew\Plugins\Actor\Cluster\GossipShardRouter;
use Yew\Plugins\Actor\Cluster\GossipTransport;
use Yew\Plugins\Actor\Cluster\PooledTcpRemoteTransport;
use Yew\Plugins\Actor\Cluster\RemoteTransport;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use Yew\Core\Log\Log;
use ReflectionClass;
use ReflectionParameter;

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
    /**
     * Resolve a "%token%" placeholder against the cluster config subtree.
     */
    private function resolvePlaceholders($value, array $clusterCfg)
    {
        if (is_string($value) && str_starts_with($value, '%') && str_ends_with($value, '%')) {
            $key = substr($value, 1, -1);
            return $clusterCfg[$key] ?? $value;
        }
        return $value;
    }

    /**
     * Build a cluster service from a declarative definition.
     *
     * Expected shape (all keys optional; falls back to $defaults):
     *   [ "class" => FQCN, "args" => [ paramName => value, ... ] ]
     *
     * Named args are matched to the constructor parameters by name (so the YAML
     * author does not need to care about parameter order) and "%token%" strings
     * are resolved against the `yew.actor.cluster` config subtree.
     *
     * @param array $definition  user-provided definition from yml
     * @param array $defaults     [ "class" => FQCN, "args" => [...] ]
     * @param array $clusterCfg  the `yew.actor.cluster` config (for placeholders)
     * @return object
     */
    private function buildService(array $definition, array $defaults, array $clusterCfg): object
    {
        $class = (string) ($definition["class"] ?? $defaults["class"]);
        $namedArgs = array_merge($defaults["args"] ?? [], $definition["args"] ?? []);

        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        $positional = [];
        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $param) {
                /** @var ReflectionParameter $param */
                $name = $param->getName();
                if (array_key_exists($name, $namedArgs)) {
                    $positional[] = $this->resolvePlaceholders($namedArgs[$name], $clusterCfg);
                } elseif ($param->isVariadic()) {
                    // skip
                } elseif ($param->isOptional()) {
                    $positional[] = $param->getDefaultValue();
                } else {
                    throw new \RuntimeException(
                        "Cluster service [$class] requires constructor arg '\$$name' but it was not provided"
                    );
                }
            }
        }
        return $ref->newInstanceArgs($positional);
    }

    /**
     * Assemble and start the cluster subsystem from declarative config.
     *
     * Builds the gossip state, UDP transport, shard router, cross-node TCP
     * transport (and optional cluster actor store) via {@see buildService}, then
     * wires them into the framework-managed multi-port listeners declared under
     * `yew.port` (cluster-gossip / cluster-tcp) so the framework owns the
     * sockets. Finally starts the failure-detection + gossip ticker.
     *
     * @return void
     */
    private function startCluster(): void
    {
        $cfg = $this->actorConfig;
        $clusterCfg = (array) (Server::$instance->getConfigContext()->get("yew.actor.cluster") ?? []);
        $services = (array) ($clusterCfg["services"] ?? []);

        $state = $this->buildService(
            (array) ($services["state"] ?? []),
            [
                "class" => GossipClusterState::class,
                "args" => [
                    "nodeId" => $cfg->getClusterNodeId(),
                    "suspectAfter" => $cfg->getClusterSuspectAfter(),
                    "downAfter" => $cfg->getClusterDownAfter(),
                ],
            ],
            $clusterCfg
        );
        if (!$state instanceof GossipClusterState) {
            throw new \RuntimeException("cluster.state must be an instance of " . GossipClusterState::class);
        }
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
            $clusterStore = $this->buildService(
                (array) ($services["store"] ?? []),
                [
                    "class" => ClusterActorStore::class,
                    "args" => [
                        "local" => new FileActorStore($cfg->getPersistenceDir()),
                        "replicationFactor" => $cfg->getClusterReplicationFactor(),
                    ],
                ],
                $clusterCfg
            );
            if (!$clusterStore instanceof ClusterActorStore) {
                throw new \RuntimeException("cluster.store must be an instance of " . ClusterActorStore::class);
            }
            $clusterStore->setCluster($state);
            $state->setActorStore($clusterStore);
            // Register as a container singleton so Actor::injectedStore is DI-injected.
            DISet(ClusterActorStore::class, $clusterStore);
        }

        // Real UDP gossip wire layer.
        $udp = $this->buildService(
            (array) ($services["gossip"] ?? []),
            [
                "class" => UdpGossipTransport::class,
                "args" => [
                    "bindHost" => $cfg->getClusterGossipHost(),
                    "bindPort" => $cfg->getClusterGossipPort() ?: ($cfg->getClusterPort() + 1000),
                    "broadcastTarget" => $cfg->getClusterGossipBroadcast(),
                ],
            ],
            $clusterCfg
        );
        if (!$udp instanceof GossipTransport) {
            throw new \RuntimeException("cluster.gossip must implement " . GossipTransport::class);
        }

        // If the UDP gossip port is declared in yew.port, run the transport in
        // framework-managed mode: the framework binds the socket (Swoole
        // multi-port) and feeds datagrams to the state through the port. Managed
        // mode MUST be enabled BEFORE $udp->start() so it does not self-bind a
        // socket that would collide with the framework listener on the same port.
        $gossipPort = Server::$instance->getPortManager()->getPortFromName(ClusterGossipUdpPort::NAME);
        if ($gossipPort instanceof ClusterGossipUdpPort) {
            $udp->setManaged(true);
            $udp->setSender(function (string $host, int $port, string $payload) {
                $swoole = Server::$instance->getServer();
                if ($swoole !== null) {
                    $swoole->sendto($host, $port, $payload);
                }
            });
        } else {
            Log::warning(
                "cluster: UDP port '" . ClusterGossipUdpPort::NAME
                . "' not declared in yew.port; falling back to self-bound gossip socket"
            );
        }
        $state->start($udp, $cfg->getClusterSeeds());

        // Now that the state owns the (managed) transport, link the framework
        // port so inbound datagrams are routed into the state.
        if ($gossipPort instanceof ClusterGossipUdpPort) {
            $gossipPort->setClusterState($state);
        }

        $localNode = new ClusterNode(
            $cfg->getClusterNodeId(),
            $cfg->getClusterHost(),
            $cfg->getClusterPort(),
            true
        );
        /** @var GossipShardRouter $router */
        $router = $this->buildService(
            (array) ($services["router"] ?? []),
            [
                "class" => GossipShardRouter::class,
                "args" => [
                    "cluster" => $state,
                    "localNode" => $localNode,
                    "replicas" => $cfg->getRoutingReplicas(),
                ],
            ],
            $clusterCfg
        );
        if (!$router instanceof GossipShardRouter) {
            throw new \RuntimeException("cluster.router must be an instance of " . GossipShardRouter::class);
        }
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
        /** @var PooledTcpRemoteTransport $transport */
        $transport = $this->buildService(
            (array) ($services["transport"] ?? []),
            [
                "class" => PooledTcpRemoteTransport::class,
                "args" => [
                    "host" => $cfg->getClusterHost(),
                    "port" => $cfg->getClusterPort(),
                    "localNodeId" => $cfg->getClusterNodeId(),
                    "poolSize" => $cfg->getClusterPoolSize(),
                ],
            ],
            $clusterCfg
        );
        if (!$transport instanceof RemoteTransport) {
            throw new \RuntimeException("cluster.transport must implement " . RemoteTransport::class);
        }

        // If the TCP cluster port is declared in yew.port, the framework binds
        // the socket (Swoole multi-port) and forwards inbound connections to the
        // transport; the transport no longer starts its own server.
        $tcpPort = Server::$instance->getPortManager()->getPortFromName(ClusterTcpPort::NAME);
        if ($tcpPort instanceof ClusterTcpPort) {
            $tcpPort->setTransport($transport);
        } else {
            Log::warning(
                "cluster: TCP port '" . ClusterTcpPort::NAME
                . "' not declared in yew.port; cross-node inbound actor calls will not be served"
            );
        }
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