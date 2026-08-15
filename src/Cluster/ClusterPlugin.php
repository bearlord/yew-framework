<?php
/**
 * Cluster plugin.
 *
 * Lives under Yew\Cluster (alongside the other cluster package classes) so the
 * actor layer can pull its published primitives via the Yew\Cluster\... imports.
 */

namespace Yew\Cluster;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Yew;
use Yew\Cluster\ClusterConfig;
use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\GossipShardRouter;
use Yew\Cluster\Router\IpcShardRouter;
use Yew\Cluster\State\ClusterNode;
use Yew\Cluster\State\ClusterState;
use Yew\Cluster\State\ClusterStateProcess;
use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\State\IpcGossipState;
use Yew\Cluster\State\NodeKey;
use Yew\Cluster\Persistence\LocalReplicaTransport;
use Yew\Cluster\Persistence\IpcReplicaTransport;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use Yew\Plugins\Actor\ActorConfig;
use Yew\Plugins\Actor\ActorPlugin;
use Yew\Plugins\Ipc\IpcPlugin;
use Yew\Cluster\Port\ClusterTcpPort;
use Yew\Cluster\Transport\GossipTransport;
use Yew\Cluster\Transport\UdpGossipTransport;
use Yew\Cluster\Transport\PooledTcpRemoteTransport;
use Yew\Cluster\Transport\RemoteTransport;
use Yew\Cluster\Transport\Transfer;
use ReflectionClass;
use ReflectionParameter;

/**
 * Wires the cluster capability (gossip membership, shard router and cross-node
 * transport) into the DI container; the actor layer picks them up later.
 */
class ClusterPlugin extends AbstractPlugin
{
    /**
     * Name of the dedicated cluster-state helper process (one per node). Mirrors
     * the Connection plugin's process model: this process is the sole owner of
     * the authoritative member view / FD / shard ring, so there is no split-brain
     * and no shared Swoole\Table. Worker processes query it via GetClusterState.
     */
    public const PROCESS_NAME = 'cluster-state';

    private ?ClusterConfig $clusterConfig = null;

    // Snapshot of `yew.cluster` taken at onAdded(); reused for "%token%"
    // resolution so config isn't re-read (or drift) before server start.
    private array $rawClusterCfg = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function getName(): string
    {
        return "Cluster";
    }

    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        // Ensure the IPC channel exists before any worker-side IpcShardRouter /
        // IpcReplicaTransport talks to the cluster-state process. ActorPlugin also
        // registers IpcPlugin; declaring the dependency here keeps ClusterPlugin
        // self-sufficient if loaded without ActorPlugin.
        $this->atAfter(IpcPlugin::class);
        // ShardRouter / ClusterActorStore are bound inside beforeServerStart();
        // ActorPlugin's beforeServerStart() resolves ShardRouter via DIGet, so
        // ClusterPlugin must run first to register the concrete IpcShardRouter.
        $this->atBefore(ActorPlugin::class);
        // Snapshot yew.cluster; the cluster package builds its own config here
        // so it stays independent of ActorPlugin.
        $this->rawClusterCfg = (array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []);
        // DIGet throws on a missing entry, so fall back to a fresh instance.
        try {
            $clusterConfig = DIGet(ClusterConfig::class);
        } catch (\Throwable $e) {
            $clusterConfig = new ClusterConfig();
        }
        $clusterConfig->buildFromArray($this->rawClusterCfg);
        DISet(ClusterConfig::class, $clusterConfig);
        $this->clusterConfig = $clusterConfig;
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        // Prefer a container instance an integrator may have replaced; fall back
        // to the one built in onAdded() so a reset container can't disable clustering.
        $this->clusterConfig = DIGet(ClusterConfig::class) ?? $this->clusterConfig;
        if ($this->clusterConfig === null || !$this->clusterConfig->isEnabled()) {
            return;
        }
        $this->start();
        // Register exactly one cluster-state helper process per node. It owns the
        // authoritative member view / FD / shard ring (see startClusterState()).
        // Kept in parallel with the legacy shared-table gossip path; actors do
        // not depend on it yet (stage 1 is additive).
        Server::$instance->addProcess(
            self::PROCESS_NAME,
            ClusterStateProcess::class,
            self::PROCESS_NAME
        );
    }

    public function beforeProcessStart(Context $context)
    {
        // Only the cluster-state helper process owns the authoritative state, the
        // gossip UDP socket and the FD tick. Worker processes resolve it exclusively
        // through IPC (GetClusterState). Bail out BEFORE the legacy multi-port
        // gossip wiring so the old path never binds the gossip UDP port in this
        // process (which would conflict with the self-managed socket below).
        $current = Server::$instance->getProcessManager()->getCurrentProcess();
        if ($current !== null && $current->getProcessName() === self::PROCESS_NAME) {
            $this->startClusterState();
            return;
        }

        // Worker path (non-cluster-state processes): wire the cluster-tcp port for
        // inbound cross-node actor calls, and start the IPC router refresh ticker so
        // the IpcShardRouter ring stays in sync with the authoritative view from the
        // cluster-state process. The gossip wire itself is owned by the cluster-state
        // process, not here.
        $this->wirePorts();
        $this->bootstrapGossip();

        $this->ready();
    }

    // Attach the cross-node actor transport to the framework-managed cluster-tcp
    // multi-port listener. Runs in the worker process because PortManager::$namePorts
    // is populated only after beforeServerStart(). The UDP gossip port
    // (cluster-gossip) is intentionally NOT wired here: since Stage 1.5 the gossip
    // wire is owned by the dedicated cluster-state helper process via a self-managed
    // UdpGossipTransport, so it must not also be bound by the framework.
    private function wirePorts(): void
    {
        $tcpPort = Server::$instance->getPortManager()->getPortFromName(ClusterTcpPort::NAME);
        if ($tcpPort instanceof ClusterTcpPort) {
            $tcpPort->setTransport(DIGet(RemoteTransport::class));
        } else {
            Server::$instance->getLog()->warning(
                "cluster: TCP port '" . ClusterTcpPort::NAME
                . "' not declared in yew.port; cross-node inbound actor calls will not be served"
            );
        }
    }

    // Resolve a "%token%" placeholder against the cluster config subtree.
    private function resolvePlaceholders($value, array $clusterCfg)
    {
        if (is_string($value) && str_starts_with($value, '%') && str_ends_with($value, '%')) {
            $key = substr($value, 1, -1);
            return $clusterCfg[$key] ?? $value;
        }
        return $value;
    }

    // Build a cluster service from a declarative definition: named args are
    // matched to constructor parameters by name, and "%token%" strings are
    // resolved against the `yew.cluster` config subtree.
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

    // Assemble the cluster subsystem from declarative config and publish its
    // primitives to the DI container. Since Stage 2 the authoritative membership
    // / FD / gossip lives in the dedicated "cluster-state" helper process; the
    // worker-side primitives here are read-through proxies over IPC:
    //   - ShardRouter  -> IpcShardRouter (routes via GetClusterState)
    //   - RemoteTransport -> PooledTcpRemoteTransport (peer host:port comes from
    //                        the resolved Location, not from membership lookups)
    //   - GossipClusterState -> IpcGossipState (multicast fan-out view, IPC-backed)
    // The old cross-worker shared Swoole\Table and the multi-worker gossip wire
    // are gone (no split-brain, single authority).
    private function start(): void
    {
        $cfg = $this->clusterConfig;
        // Reuse the subtree captured at onAdded() so placeholder resolution
        // matches the values ClusterConfig was built from.
        $clusterCfg = $this->rawClusterCfg !== []
            ? $this->rawClusterCfg
            : (array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []);
        $services = $cfg->getServices();

        $localNode = new ClusterNode(
            $cfg->getNodeId(),
            $cfg->getHost(),
            $cfg->getPort(),
            true
        );

        // IPC-backed shard router. Its ring is refreshed on a worker timer (see
        // beforeProcessStart) so topology rebalancing still fires locally.
        /** @var IpcShardRouter $router */
        $router = $this->buildService(
            (array) ($services["router"] ?? []),
            [
                "class" => IpcShardRouter::class,
                "args" => [
                    "localNode" => $localNode,
                    "replicas" => $cfg->getReplicas(),
                ],
            ],
            $clusterCfg
        );
        if (!$router instanceof IpcShardRouter) {
            throw new \RuntimeException("cluster.router must be an instance of " . IpcShardRouter::class);
        }

        // Pooled TCP transport for cross-node calls. It draws the target
        // host:port from the resolved Location (not from membership), so it is
        // independent of the gossip wire and runs unchanged in the worker.
        $transport = $this->buildService(
            (array) ($services["transport"] ?? []),
            [
                "class" => PooledTcpRemoteTransport::class,
                "args" => [
                    "host" => $cfg->getHost(),
                    "port" => $cfg->getPort(),
                    "localNodeId" => $cfg->getNodeId(),
                    "poolSize" => $cfg->getPoolSize(),
                ],
            ],
            $clusterCfg
        );
        if (!$transport instanceof RemoteTransport || !$transport instanceof Transfer) {
            throw new \RuntimeException(
                "cluster.transport must implement " . RemoteTransport::class . " and " . Transfer::class
            );
        }
        $transport->start();

        // IPC-backed cluster view for multicast fan-out (no wire of its own).
        $ipcState = new IpcGossipState($cfg->getNodeId());

        // Publish the primitives; the actor / multicast layers pull them in.
        DISet(ShardRouter::class, $router);
        DISet(RemoteTransport::class, $transport);
        DISet(GossipClusterState::class, $ipcState);

        // Worker-side ActorStore: a shared instance wired with the IPC replica
        // transport. Every actor on this worker resolves the SAME instance via
        // #[Inject]; replication and failover reads are proxied to the
        // cluster-state process (single authority, no per-worker replica drift).
        DISet(ClusterActorStore::class, static function () use ($cfg) {
            $store = new ClusterActorStore(
                new FileActorStore(DIGet(ActorConfig::class)->getPersistenceDir())
            );
            $store->setCluster(new IpcReplicaTransport());
            return $store;
        });
    }

    // Boot the worker-side cluster refresh ticker. Runs in the worker process
    // after the event loop is up. Idempotent. Keeps the IPC-backed IpcShardRouter
    // ring in sync with the authoritative view from the cluster-state process so
    // topology rebalancing (actor eviction) fires locally.
    //
    // De-duplication: only ONE process (the canonical "actor-0" actor process)
    // needs to own the periodic IPC ticker. Every other worker process still gets
    // a fresh-enough ring via the TTL-based lazy refresh inside IpcShardRouter
    // (triggered on each routing lookup), so we avoid N-1 redundant IPC timers
    // each hammering the single cluster-state process every tick.
    private function bootstrapGossip(): void
    {
        /** @var IpcShardRouter|null $router */
        $router = DIGet(ShardRouter::class);
        if (!$router instanceof IpcShardRouter) {
            return;
        }
        // Seed the initial view in every process (cheap, one-shot).
        // The cluster-state process may not be IPC-ready yet during
        // beforeProcessStart (it boots in parallel), so a timeout here is
        // expected and harmless: the periodic ticker (actor-0) and the TTL-based
        // lazy refresh (every other process, on first routing lookup) will pull a
        // fresh view once the authority is up. Swallow the IPC timeout so it does
        // not fatally abort this process's startup.
        try {
            $router->refresh();
        } catch (\Yew\Plugins\Ipc\IpcException $e) {
            // Leave the ring empty; it will be populated on the next refresh.
        }

        $current = Server::$instance->getProcessManager()->getCurrentProcess();
        $isPrimary = $current !== null && $current->getProcessName() === 'actor-0';
        if (!$isPrimary) {
            return;
        }
        $intervalMs = max(500, (int) ($this->clusterConfig->getHeartbeatInterval() * 1000));
        \Swoole\Timer::tick($intervalMs, static function () use ($router) {
            $router->refresh();
        });
    }

    /**
     * Boot the dedicated cluster-state helper process. This process is the SOLE
     * authority for membership / failure detection / shard routing for the node.
     *
     * Stage 1.5: the real gossip protocol (SYN / SYN-ACK / ACK handshake, digest
     * anti-entropy, UDP fragmentation + per-fragment retransmit, asymmetric-key
     * verification, FD) runs HERE via a mounted {@see GossipClusterState} engine
     * over a self-managed {@see UdpGossipTransport}. Because the UDP socket is
     * opened in this process (setManaged(false)), the entire protocol executes in
     * the single cluster-state process — no multi-worker gossip, no shared
     * Swoole\Table, no split-brain.
     *
     * Worker processes never touch any of this directly; they query {@see ClusterState}
     * through the {@see \Yew\Cluster\GetClusterState} IPC proxy.
     */
    private function startClusterState(): void
    {
        $cfg = $this->clusterConfig;

        // Authoritative state facade (IPC surface + engine owner).
        $state = new ClusterState(
            $cfg->getNodeId(),
            $cfg->getSuspectAfter(),
            $cfg->getDownAfter(),
            $this->resolvePeers($cfg),
            null,
            false // real heartbeats now drive FD; no optimistic hold
        );

        // Register the concrete ClusterState instance in the container the IPC
        // message processor resolves against BEFORE any other setup that could
        // throw. Otherwise an exception later in startClusterState would leave the
        // cluster-state process unable to resolve ClusterState for inbound IPC
        // calls (getMemberView, etc.), and the worker refresh tick would fatal-loop.
        $this->setToDIContainer(ClusterState::class, $state);
        Server::$instance->getContainer()->set(ClusterState::class, $state);

        // Build the proven GossipClusterState engine. No shared table is attached
        // (configureSharedView is NOT called), so it operates purely on its own
        // in-process $members — i.e. single authority, exactly as intended.
        $engine = new GossipClusterState(
            $cfg->getNodeId(),
            $cfg->getSuspectAfter(),
            $cfg->getDownAfter()
        );
        $priv = $cfg->getPrivateKey();
        $pub = $cfg->getPublicKey();
        if ($priv !== '' && $pub !== '') {
            $engine->setKey(
                NodeKey::fromPem($priv, $pub),
                $cfg->getTrustStore(),
                $cfg->getClockSkew() * 120
            );
        } else {
            $engine->setSecret($cfg->getSecret(), $cfg->getClockSkew());
        }
        $gossipPort = $cfg->getGossipPort() ?: ($cfg->getPort() + 1000);
        $engine->setGossipPort($gossipPort);
        $engine->join($cfg->getHost(), $cfg->getPort(), $cfg->getWeight(), $gossipPort);

        // Self-managed UDP socket in THIS process (no framework multi-port), so
        // the cluster-state process is the only receiver/sender of gossip traffic.
        $udp = new UdpGossipTransport(
            $cfg->getGossipHost(),
            $cfg->getGossipPort() ?: ($cfg->getPort() + 1000),
            $cfg->getGossipBroadcast()
        );
        $udp->setManaged(false);

        // Router over the engine (consumes ClusterStateInterface).
        $localNode = new ClusterNode(
            $cfg->getNodeId(), $cfg->getHost(), $cfg->getPort(), true
        );
        $router = new GossipShardRouter($engine, $localNode, $cfg->getReplicas());

        // Mount everything into the single authority process and start the wire.
        // Persist learned peers per node so a cold start can still join when all
        // static seeds are down (seed self-healing).
        $peerCacheFile = Server::$instance->getServerConfig()->getRuntimeDir()
            . DIRECTORY_SEPARATOR . 'cluster'
            . DIRECTORY_SEPARATOR . 'peers-' . $cfg->getNodeId() . '.json';
        $state->attachGossip($cfg, $engine, $udp, $router, $cfg->getSeeds(), $peerCacheFile);

        Server::$instance->getLog()->info(sprintf(
            '[cluster-state] authority process ready: node=%s seeds=[%s] peerCache=%s tick=%dms',
            $cfg->getNodeId(),
            implode(',', $cfg->getSeeds()),
            $peerCacheFile,
            max(500, (int) ($cfg->getHeartbeatInterval() * 1000))
        ));

        // Cross-node ActorStore replication + failover live HERE too: this is the
        // single process that owns the gossip replica buffer. The local store
        // ingests inbound replicas straight to disk; onNodeDown records the dead
        // node so workers can query which actors to resurrect via IPC.
        $store = new ClusterActorStore(
            new FileActorStore(DIGet(ActorConfig::class)->getPersistenceDir())
        );
        $store->setCluster(new LocalReplicaTransport($state));
        $state->setActorStore($store);
        $state->onNodeDown(function (string $deadNodeId) use ($state, $cfg) {
            Server::$instance->getLog()->info(
                "[cluster-state] peer down: {$deadNodeId}; failover actors: "
                . implode(',', $state->getFailoverActors($deadNodeId))
            );
            // Workers pick this up via IpcShardRouter::refresh() membership drop +
            // clusterFailoverActors(); no direct push needed.
        });

        $intervalMs = max(500, (int) ($cfg->getHeartbeatInterval() * 1000));
        \Swoole\Timer::tick($intervalMs, static function () use ($state) {
            $state->tick();
        });

        $this->ready();
    }

    /**
     * Resolve static peer endpoints from config. Prefers an explicit `peers` map
     * (nodeId => "host:port"); otherwise maps each seed to a synthetic id. Stage 1
     * only seeds the view; gossip (stage 1.5) will replace this with discovered
     * membership.
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
