<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Cluster;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Cluster\ClusterConfig;
use Yew\Cluster\State\ClusterNode;
use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\State\NodeKey;
use Yew\Cluster\Port\ClusterGossipUdpPort;
use Yew\Cluster\Port\ClusterTcpPort;
use Yew\Cluster\Transport\UdpGossipTransport;
use Yew\Cluster\Transport\GossipTransport;
use Yew\Cluster\Transport\PooledTcpRemoteTransport;
use Yew\Cluster\Transport\RemoteTransport;
use Yew\Cluster\Transport\Transfer;

use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\GossipShardRouter;
use Yew\Core\Memory\CrossProcess\Table;
use ReflectionClass;
use ReflectionParameter;

/**
 * Wires the cluster capability (gossip membership, shard router and cross-node
 * transport) into the DI container; the actor layer picks them up later.
 */
class ClusterPlugin extends AbstractPlugin
{
    private ?ClusterConfig $clusterConfig = null;

    // Snapshot of `yew.cluster` taken at onAdded(); reused for "%token%"
    // resolution so config isn't re-read (or drift) before server start.
    private array $rawClusterCfg = [];

    // Gossip bootstrap deferred until the worker process (beforeProcessStart),
    // where the Swoole event loop is already running. Must NOT be started in
    // beforeServerStart(): that activates the coroutine runtime first and makes
    // Swoole\Http\Server::start() fail with "The event-loop has already been created".
    private ?array $deferredGossip = null;

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
    }

    public function beforeProcessStart(Context $context)
    {
        // Attach multi-port listeners BEFORE booting gossip, so the UDP transport
        // is flagged managed and doesn't open a self-bound socket. Both run here
        // (worker process) where the event loop is live.
        $this->wirePorts();
        $this->bootstrapGossip();
        $this->ready();
    }

    // Attach the gossip state / transport to the framework-managed multi-port
    // listeners (cluster-gossip UDP, cluster-tcp TCP). Runs in the worker process
    // because PortManager::$namePorts is populated only after beforeServerStart().
    private function wirePorts(): void
    {
        if ($this->deferredGossip === null) {
            return;
        }
        /** @var GossipClusterState $state */
        $state = $this->deferredGossip["state"];
        /** @var UdpGossipTransport $udp */
        $udp = $this->deferredGossip["udp"];

        $gossipPort = Server::$instance->getPortManager()->getPortFromName(ClusterGossipUdpPort::NAME);
        if ($gossipPort instanceof ClusterGossipUdpPort) {
            $udp->setManaged(true);
            $udp->setSender(function (string $host, int $port, string $payload) {
                $swoole = Server::$instance->getServer();
                if ($swoole !== null) {
                    $swoole->sendto($host, $port, $payload);
                }
            });
            $gossipPort->setClusterState($state);
        } else {
            Server::$instance->getLog()->warning(
                "cluster: UDP port '" . ClusterGossipUdpPort::NAME
                . "' not declared in yew.port; falling back to self-bound gossip socket"
            );
        }

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
    // three primitives (ShardRouter, RemoteTransport, GossipClusterState) to the
    // DI container. Does not touch the actor runtime — the actor layer pulls
    // these and wires them in itself.
    private function start(): void
    {
        $cfg = $this->clusterConfig;
        // Reuse the subtree captured at onAdded() so placeholder resolution
        // matches the values ClusterConfig was built from.
        $clusterCfg = $this->rawClusterCfg !== []
            ? $this->rawClusterCfg
            : (array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []);
        $services = $cfg->getServices();

        $state = $this->buildService(
            (array) ($services["state"] ?? []),
            [
                "class" => GossipClusterState::class,
                "args" => [
                    "localNodeId" => $cfg->getNodeId(),
                    "suspectAfter" => $cfg->getSuspectAfter(),
                    "downAfter" => $cfg->getDownAfter(),
                ],
            ],
            $clusterCfg
        );
        if (!$state instanceof GossipClusterState) {
            throw new \RuntimeException("cluster.state must be an instance of " . GossipClusterState::class);
        }
        // Prefer per-node asymmetric keys; fall back to the shared HMAC secret.
        $priv = $cfg->getPrivateKey();
        $pub = $cfg->getPublicKey();
        if ($priv !== '' && $pub !== '') {
            $key = NodeKey::fromPem($priv, $pub);
            $state->setKey($key, $cfg->getTrustStore(), $cfg->getClockSkew() * 120);
        } else {
            $state->setSecret($cfg->getSecret(), $cfg->getClockSkew());
        }
        $state->join($cfg->getHost(), $cfg->getPort(), $cfg->getWeight());

        // Cross-worker shared membership view (Swoole\Table / shared memory).
        // Created here in beforeServerStart, i.e. BEFORE the workers fork, so the
        // table is shared by every worker process. The designated gossip worker
        // (worker-0) writes converged membership into it; all other workers read
        // it so GossipShardRouter routes against one consistent view regardless
        // of which worker received a given UDP gossip packet.
        $sharedTable = self::createSharedMemberTable(4096);
        DISet("cluster.memberTable", $sharedTable);

        // Real UDP gossip wire layer.
        $udp = $this->buildService(
            (array) ($services["gossip"] ?? []),
            [
                "class" => UdpGossipTransport::class,
                "args" => [
                    "bindHost" => $cfg->getGossipHost(),
                    "bindPort" => $cfg->getGossipPort() ?: ($cfg->getPort() + 1000),
                    "broadcastTarget" => $cfg->getGossipBroadcast(),
                ],
            ],
            $clusterCfg
        );
        if (!$udp instanceof GossipTransport) {
            throw new \RuntimeException("cluster.gossip must implement " . GossipTransport::class);
        }

        // Ports aren't registered yet (namePorts is populated only after
        // beforeServerStart), so defer the wiring to wirePorts() in the worker.
        $this->deferredGossip = [
            "state" => $state,
            "udp" => $udp,
            "seeds" => $cfg->getSeeds(),
            "heartbeat" => $cfg->getHeartbeatInterval(),
        ];

        $localNode = new ClusterNode(
            $cfg->getNodeId(),
            $cfg->getHost(),
            $cfg->getPort(),
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
                    "replicas" => $cfg->getReplicas(),
                ],
            ],
            $clusterCfg
        );
        if (!$router instanceof GossipShardRouter) {
            throw new \RuntimeException("cluster.router must be an instance of " . GossipShardRouter::class);
        }

        // Pooled TCP transport for cross-node calls.
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

        // cluster-tcp wiring (setTransport) is deferred to wirePorts() for the
        // same reason as the gossip port: namePorts isn't ready in beforeServerStart.
        $transport->start();

        // Publish the three primitives; the actor layer pulls them from the
        // container and wires them into the actor runtime.
        DISet(ShardRouter::class, $router);
        DISet(RemoteTransport::class, $transport);
        DISet(GossipClusterState::class, $state);
    }

    // Boot the gossip receiver coroutine + failure-detection ticker. Runs once
    // in the worker process after the event loop is up. Idempotent.
    private function bootstrapGossip(): void
    {
        if ($this->deferredGossip === null) {
            return;
        }
        $deferred = $this->deferredGossip;
        $this->deferredGossip = null;

        /** @var GossipClusterState $state */
        $state = $deferred["state"];
        /** @var GossipTransport $udp */
        $udp = $deferred["udp"];
        $seeds = $deferred["seeds"];
        $heartbeat = $deferred["heartbeat"];

        // Only worker-0 owns the authoritative gossip state. In a multi-worker
        // deployment every worker may receive UDP packets, but convergence must
        // happen in exactly one place; the others are pure consumers of the
        // shared table (set up in start()). This keeps all workers' routers in
        // sync without duplicating the membership merge logic per worker.
        $workerId = $this->getWorkerId();
        $isGossipWorker = ($workerId === 0);

        /** @var Table|null $sharedTable */
        $sharedTable = DIGet("cluster.memberTable");
        if ($sharedTable instanceof Table) {
            $state->configureSharedView($sharedTable, $isGossipWorker);
        }

        if (!$isGossipWorker) {
            // Seed the local node row so this worker can route to itself even
            // before the first table sync from worker-0 lands.
            if ($sharedTable instanceof Table) {
                $local = $state->getNode($state->getLocalNodeId());
                if ($local !== null) {
                    $sharedTable->set($local->nodeId, $local->toRow());
                }
            }
            // Consumer workers never receive the membership-change notification
            // (that only fires on the gossip worker). Poll the shared table so
            // the router ring converges to the authoritative view.
            \Swoole\Timer::tick(2000, function () use ($state) {
                $state->refreshView();
            });
            return;
        }

        $state->start($udp, $seeds);

        \Swoole\Timer::tick((int) ($heartbeat * 1000), function () use ($state) {
            $state->tick();
        });
    }

    private function getWorkerId(): int
    {
        $server = Server::$instance->getServer();
        if ($server === null) {
            return 0;
        }
        // NOTE: Yew overwrites $server->worker_id with its OWN process id
        // (see ProcessManager::setCurrentProcessId), so the property no longer
        // reflects the Swoole worker index. Use the native Swoole accessor,
        // which reads the internal C value, to pick the single gossip worker.
        if (method_exists($server, 'getWorkerId')) {
            $id = $server->getWorkerId();
            if (is_int($id) && $id >= 0) {
                return $id;
            }
        }
        return 0;
    }

    /**
     * Build the shared cross-worker membership table. Columns mirror
     * ClusterMember::toRow() so rows round-trip through fromRow().
     */
    private static function createSharedMemberTable(int $size): Table
    {
        $table = new Table($size);
        $table->column("nodeId", Table::TYPE_STRING, 64);
        $table->column("host", Table::TYPE_STRING, 64);
        $table->column("port", Table::TYPE_INT, 4);
        $table->column("weight", Table::TYPE_INT, 2);
        $table->column("status", Table::TYPE_STRING, 8);
        $table->column("lastHeartbeat", Table::TYPE_INT, 8);
        $table->column("incarnation", Table::TYPE_INT, 8);
        $table->column("publicKey", Table::TYPE_STRING, 2048);
        $table->create();
        return $table;
    }
}
