<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Log\Log;
use Yew\Coroutine\Server\Server;
use Yew\Cluster\State\ClusterNode;
use Yew\Cluster\State\GossipClusterState;
use Yew\Cluster\State\NodeKey;
use Yew\Cluster\Port\ClusterGossipUdpPort;
use Yew\Cluster\Port\ClusterTcpPort;
use Yew\Cluster\Transport\UdpGossipTransport;
use Yew\Cluster\Transport\GossipTransport;
use Yew\Cluster\Transport\PooledTcpRemoteTransport;
use Yew\Cluster\Transport\RemoteTransport;
use Yew\Cluster\Router\ShardRouter;
use Yew\Cluster\Router\GossipShardRouter;
use ReflectionClass;
use ReflectionParameter;

/**
 * Standalone cluster plugin. Owns the gossip membership service, the
 * consistent-hash shard router and the cross-node TCP transport.
 *
 * This plugin is deliberately actor-agnostic: it only assembles the cluster
 * primitives and publishes them through the DI container as the
 * {@see ShardRouter}, {@see RemoteTransport} and {@see GossipClusterState}
 * interfaces. The actor layer (ActorPlugin) later pulls these from the container
 * and wires them into the actor runtime. The dependency direction is strictly
 * actor -> cluster; cluster never references the actor package.
 */
class ClusterPlugin extends AbstractPlugin
{
    /**
     * @var ClusterConfig|null
     */
    private ?ClusterConfig $clusterConfig = null;

    /**
     * Raw `yew.cluster` subtree, captured once at onAdded() and reused for
     * "%token%" placeholder resolution so the config is not re-read (and cannot
     * drift) between registration and server start.
     *
     * @var array
     */
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
        // Build the top-level cluster config from the "yew.cluster" subtree and
        // publish it. Done here (not in ActorPlugin) so the cluster package is
        // fully self-contained. ActorPlugin only consumes it via DI.
        $this->rawClusterCfg = (array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []);
        $clusterConfig = DIGet(ClusterConfig::class) ?? new ClusterConfig();
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
        // Prefer the container (an integrator may have replaced the instance),
        // but fall back to the one built in onAdded() so a bypassed/reset
        // container cannot silently disable clustering.
        $this->clusterConfig = DIGet(ClusterConfig::class) ?? $this->clusterConfig;
        if ($this->clusterConfig === null || !$this->clusterConfig->isEnabled()) {
            return;
        }
        $this->start();
    }

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
     * Named args are matched to the constructor parameters by name and
     * "%token%" strings are resolved against the `yew.cluster` config subtree.
     *
     * @param array $definition  user-provided definition from yml
     * @param array $defaults     [ "class" => FQCN, "args" => [...] ]
     * @param array $clusterCfg  the `yew.cluster` config (for placeholders)
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
     * Builds the gossip state, UDP transport, shard router and cross-node TCP
     * transport via {@see buildService}, wires them into the framework-managed
     * multi-port listeners, publishes the three primitives through the DI
     * container, and starts the failure-detection ticker.
     *
     * It deliberately does NOT touch the actor runtime; the actor layer pulls
     * {@see ShardRouter}, {@see RemoteTransport} and {@see GossipClusterState}
     * from the container and wires them in itself.
     *
     * @return void
     */
    private function start(): void
    {
        $cfg = $this->clusterConfig;
        // Reuse the subtree captured at onAdded() (see $rawClusterCfg) instead of
        // re-reading it here, so placeholder resolution always matches the values
        // that ClusterConfig was actually built from. Fall back to a fresh read
        // only if onAdded() never ran (e.g. plugin constructed manually).
        $clusterCfg = $this->rawClusterCfg !== []
            ? $this->rawClusterCfg
            : (array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []);
        $services = $cfg->getServices();

        $state = $this->buildService(
            (array) ($services["state"] ?? []),
            [
                "class" => GossipClusterState::class,
                "args" => [
                    "nodeId" => $cfg->getNodeId(),
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
        $state->start($udp, $cfg->getSeeds());

        if ($gossipPort instanceof ClusterGossipUdpPort) {
            $gossipPort->setClusterState($state);
        }

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
        /** @var PooledTcpRemoteTransport $transport */
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
        if (!$transport instanceof RemoteTransport) {
            throw new \RuntimeException("cluster.transport must implement " . RemoteTransport::class);
        }

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

        // Publish the three cluster primitives. The actor layer pulls these from
        // the container and wires them into the actor runtime (setShardRouter,
        // setRemoteTransport, cluster store, rebalance/failover hooks).
        DISet(ShardRouter::class, $router);
        DISet(RemoteTransport::class, $transport);
        DISet(GossipClusterState::class, $state);

        // Failure-detection + gossip ticker.
        \Swoole\Timer::tick((int) ($cfg->getHeartbeatInterval() * 1000), function () use ($state) {
            $state->tick();
        });
    }
}
