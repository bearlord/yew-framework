<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

use Yew\Core\Plugins\Config\BaseConfig;

/**
 * Top-level cluster configuration, sourced from the `yew.cluster` config
 * subtree (decoupled from `yew.actor`). Holds gossip membership, shard
 * routing and cross-node transport settings.
 */
class ClusterConfig extends BaseConfig
{
    const KEY = "cluster";

    /**
     * @var bool Whether cluster sharding (gossip membership + rebalance) is on
     */
    protected bool $enabled = false;

    /**
     * @var string Stable node id for this process in the cluster
     */
    protected string $nodeId = 'local';

    /**
     * @var string Bind host advertised to peers
     */
    protected string $host = '127.0.0.1';

    /**
     * @var int Bind port advertised to peers
     */
    protected int $port = 0;

    /**
     * @var int Weighted capacity of this node (more shards the higher)
     */
    protected int $weight = 1;

    /**
     * @var int Seconds of missed heartbeat before a node is suspected
     */
    protected int $suspectAfter = 3;

    /**
     * @var int Seconds of missed heartbeat before a node is marked down
     */
    protected int $downAfter = 8;

    /**
     * @var float Seconds between membership heartbeats / failure-detection ticks
     */
    protected float $heartbeatInterval = 1.0;

    /**
     * @var string UDP bind address for gossip (host)
     */
    protected string $gossipHost = '0.0.0.0';

    /**
     * @var int UDP bind port for gossip
     */
    protected int $gossipPort = 0;

    /**
     * @var string UDP broadcast/multicast target ("host:port")
     */
    protected string $gossipBroadcast = '239.0.0.1:49999';

    /**
     * @var array<int,string> Seed peers ("host:port") for first contact
     */
    protected array $seeds = [];

    /**
     * @var int Remote transport TCP connection pool size per node
     */
    protected int $poolSize = 16;

    /**
     * @var string Shared HMAC secret for gossip message signing (anti-spoofing)
     */
    protected string $secret = '';

    /**
     * @var int Allowed clock skew (seconds) for gossip message freshness
     */
    protected int $clockSkew = 30;

    /**
     * @var string This node's private key PEM (asymmetric, per-node certificate).
     *             When set, gossip switches from shared HMAC to per-node signing.
     */
    protected string $privateKey = '';

    /**
     * @var string This node's public key PEM (paired with privateKey).
     */
    protected string $publicKey = '';

    /**
     * @var array<string,string> Pinned trust store: nodeId => public-key PEM.
     *             When non-empty, only nodes whose pubkey is pinned are accepted.
     */
    protected array $trustStore = [];

    /**
     * @var int Number of replicas an actor's events/snapshots are replicated to
     *          (besides the owning node). Quorum for a read = 1 replica present.
     */
    protected int $replicationFactor = 2;

    /**
     * @var array Declarative service overrides (class + args per role).
     *            Roles: state / gossip / router / transport / store.
     */
    protected array $services = [];

    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * Build the cluster config from the "yew.cluster" subtree.
     *
     * @return void
     */
    public function buildFromArray(array $cfg): void
    {
        $this->enabled = (bool) ($cfg["enabled"] ?? false);
        $this->nodeId = (string) ($cfg["nodeId"] ?? ('node-' . gethostname()));
        $this->host = (string) ($cfg["host"] ?? "127.0.0.1");
        $this->port = (int) ($cfg["port"] ?? 0);
        $this->weight = (int) ($cfg["weight"] ?? 1);
        $this->suspectAfter = (int) ($cfg["suspectAfter"] ?? 3);
        $this->downAfter = (int) ($cfg["downAfter"] ?? 8);
        $this->heartbeatInterval = (float) ($cfg["heartbeatInterval"] ?? 1.0);
        $this->gossipHost = (string) ($cfg["gossipHost"] ?? "0.0.0.0");
        $this->gossipPort = (int) ($cfg["gossipPort"] ?? 0);
        $this->gossipBroadcast = (string) ($cfg["gossipBroadcast"] ?? "239.0.0.1:49999");
        $seeds = $cfg["seeds"] ?? [];
        $this->seeds = is_array($seeds) ? array_values(array_map('strval', $seeds)) : [];
        $this->poolSize = (int) ($cfg["poolSize"] ?? 16);
        $this->secret = (string) ($cfg["secret"] ?? '');
        $this->clockSkew = (int) ($cfg["clockSkew"] ?? 30);
        $this->privateKey = (string) ($cfg["privateKey"] ?? '');
        $this->publicKey = (string) ($cfg["publicKey"] ?? '');
        $this->trustStore = (array) ($cfg["trustStore"] ?? []);
        $this->replicationFactor = (int) ($cfg["replicationFactor"] ?? 2);
        $this->services = (array) ($cfg["services"] ?? []);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function setNodeId(string $nodeId): void
    {
        $this->nodeId = $nodeId;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
    }

    public function getSuspectAfter(): int
    {
        return $this->suspectAfter;
    }

    public function setSuspectAfter(int $suspectAfter): void
    {
        $this->suspectAfter = $suspectAfter;
    }

    public function getDownAfter(): int
    {
        return $this->downAfter;
    }

    public function setDownAfter(int $downAfter): void
    {
        $this->downAfter = $downAfter;
    }

    public function getHeartbeatInterval(): float
    {
        return $this->heartbeatInterval;
    }

    public function setHeartbeatInterval(float $heartbeatInterval): void
    {
        $this->heartbeatInterval = $heartbeatInterval;
    }

    public function getGossipHost(): string
    {
        return $this->gossipHost;
    }

    public function setGossipHost(string $gossipHost): void
    {
        $this->gossipHost = $gossipHost;
    }

    public function getGossipPort(): int
    {
        return $this->gossipPort;
    }

    public function setGossipPort(int $gossipPort): void
    {
        $this->gossipPort = $gossipPort;
    }

    public function getGossipBroadcast(): string
    {
        return $this->gossipBroadcast;
    }

    public function setGossipBroadcast(string $gossipBroadcast): void
    {
        $this->gossipBroadcast = $gossipBroadcast;
    }

    /**
     * @return array<int,string>
     */
    public function getSeeds(): array
    {
        return $this->seeds;
    }

    /**
     * @param array<int,string> $seeds
     */
    public function setSeeds(array $seeds): void
    {
        $this->seeds = $seeds;
    }

    public function getPoolSize(): int
    {
        return $this->poolSize;
    }

    public function setPoolSize(int $poolSize): void
    {
        $this->poolSize = $poolSize;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    public function getClockSkew(): int
    {
        return $this->clockSkew;
    }

    public function setClockSkew(int $clockSkew): void
    {
        $this->clockSkew = $clockSkew;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @return array<string,string>
     */
    public function getTrustStore(): array
    {
        return $this->trustStore;
    }

    /**
     * @param array<string,string> $trustStore
     */
    public function setTrustStore(array $trustStore): void
    {
        $this->trustStore = $trustStore;
    }

    public function getReplicationFactor(): int
    {
        return $this->replicationFactor;
    }

    public function setReplicationFactor(int $replicationFactor): void
    {
        $this->replicationFactor = $replicationFactor;
    }

    /**
     * @return array Declarative service overrides per role.
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @param array $services
     */
    public function setServices(array $services): void
    {
        $this->services = $services;
    }
}
