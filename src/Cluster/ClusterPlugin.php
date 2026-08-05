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
use Yew\Cluster\Router\GossipShardRouter;
use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\ActorConfig;
use Yew\Plugins\Actor\ActorManager;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use ReflectionClass;
use ReflectionParameter;

/**
 * Standalone cluster plugin. Owns the gossip membership service, the
 * consistent-hash shard router and the cross-node TCP transport. It is fed by
 * the top-level {@see ClusterConfig} (decoupled from the actor config) but
 * still collaborates with {@see ActorConfig} for the persistence-backed
 * cross-node store (ClusterActorStore wraps the actor's FileActorStore).
 */
class ClusterPlugin extends AbstractPlugin
{
    /**
     * @var ClusterConfig|null
     */
    private ?ClusterConfig $clusterConfig;

    /**
     * @var ActorConfig|null
     */
    private ?ActorConfig $actorConfig;

    /**
     * @var ActorManager|null
     */
    private ?ActorManager $actorManager = null;

    /**
     * @var callable(string):void|null User-registered failover handler.
     */
    private $failoverHandler = null;

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
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        $this->clusterConfig = DIGet(ClusterConfig::class);
        $this->actorConfig = DIGet(ActorConfig::class);
        if ($this->clusterConfig === null || !$this->clusterConfig->isEnabled()) {
            return;
        }
        $this->actorManager = ActorManager::getInstance();
        $this->start();
    }

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
     * Builds the gossip state, UDP transport, shard router, cross-node TCP
     * transport (and optional cluster actor store) via {@see buildService},
     * wires them into the framework-managed multi-port listeners, and starts
     * the failure-detection ticker.
     *
     * @return void
     */
    private function start(): void
    {
        $cfg = $this->clusterConfig;
        $actorCfg = $this->actorConfig;
        $clusterCfg = (array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []);
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

        // Cross-node durable store: when cluster + persistence + storeEnabled are
        // all on, wrap the local FileActorStore in a cluster-aware one that
        // replicates every actor's events/snapshots to peer nodes.
        $clusterStore = null;
        if ($actorCfg !== null && $actorCfg->isPersistenceEnabled() && $cfg->getReplicationFactor() > 0) {
            $clusterStore = $this->buildService(
                (array) ($services["store"] ?? []),
                [
                    "class" => ClusterActorStore::class,
                    "args" => [
                        "local" => new FileActorStore($actorCfg->getPersistenceDir()),
                        "replicationFactor" => $cfg->getReplicationFactor(),
                    ],
                ],
                $clusterCfg
            );
            if (!$clusterStore instanceof ClusterActorStore) {
                throw new \RuntimeException("cluster.store must be an instance of " . ClusterActorStore::class);
            }
            $clusterStore->setCluster($state);
            $state->setActorStore($clusterStore);
            DISet(ClusterActorStore::class, $clusterStore);
        }

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
                    "replicas" => $actorCfg !== null ? $actorCfg->getRoutingReplicas() : 128,
                ],
            ],
            $clusterCfg
        );
        if (!$router instanceof GossipShardRouter) {
            throw new \RuntimeException("cluster.router must be an instance of " . GossipShardRouter::class);
        }
        $this->actorManager->setShardRouter($router);

        if ($clusterStore !== null) {
            $state->onNodeDown(function (string $deadNodeId) use ($router, $state) {
                $this->failoverFrom($deadNodeId, $router, $state);
            });
        }

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
        $this->actorManager->setRemoteTransport($transport);

        // Keep a handle so external code (debug endpoints) can inspect it.
        DISet(GossipClusterState::class, $state);

        // Failure-detection + gossip ticker.
        \Swoole\Timer::tick((int) ($cfg->getHeartbeatInterval() * 1000), function () use ($state) {
            $state->tick();
        });
    }

    /**
     * Cross-node supervision: a peer node died. For every actor whose replicated
     * store this node holds and that now hashes to this node (per the consistent
     * hash ring), ask the registered handler to resurrect it.
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
            if ($router->getNode($actorName) !== $localNodeId) {
                continue;
            }
            if (ActorManager::getInstance()->getActorRaw($actorName) !== null) {
                continue;
            }
            ($this->failoverHandler)($actorName);
        }
    }
}
