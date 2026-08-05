<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Cluster\ClusterConfig;


class ActorConfig extends BaseConfig
{
    const KEY = "actor";

    const GROUP_NAME = "ActorGroup";
    
    /**
     * @var int Actor max count
     */
    protected int $maxCount = 10000;

    /**
     * @var int Actor max class count
     */
    protected int $maxClassCount = 100;

    /**
     * @var int Actor worker count
     */
    protected int $workerCount = 1;

    /**
     * @var int Actor mailbox capacity
     */
    protected int $mailboxCapacity = 100;

    /**
     * @var string Mailbox overflow strategy: "block" | "drop" | "fail"
     */
    protected string $mailboxOverflow = 'block';

    /**
     * @var float Max seconds a blocking push waits for free mailbox space (block strategy only)
     */
    protected float $mailboxPushTimeout = 1.0;

    /**
     * @var string Supervisor strategy on actor failure: "restart" | "resume" | "stop" | "escalate"
     */
    protected string $supervisorStrategy = 'restart';

    /**
     * @var int Max consecutive restarts before escalating (restart strategy only)
     */
    protected int $supervisorMaxRetries = 3;

    /**
     * @var string Supervision scope: "one-for-one" | "all-for-one"
     *  - one-for-one: only the failing child is affected
     *  - all-for-one:  all siblings restart/stop together when one fails
     */
    protected string $supervisorMode = 'one-for-one';

    /**
     * @var bool Whether event-sourcing persistence is enabled for actors
     */
    protected bool $persistenceEnabled = false;

    /**
     * @var string Directory used by the file-based persistence backend
     */
    protected string $persistenceDir = '/tmp/yew-actor-store';

    /**
     * @var string Process selection strategy for new actors:
     *             "round-robin" | "consistent-hash" | "least-loaded"
     */
    protected string $routingStrategy = 'round-robin';

    /**
     * @var int Virtual replicas per node for consistent-hash routing
     */
    protected int $routingReplicas = 128;

    /**
     * @var string Execution model / dispatcher: "coroutine" | "pinned" | "thread-pool"
     */
    protected string $dispatcher = 'coroutine';

    /**
     * @var int Worker size for the thread-pool dispatcher (when thread support exists)
     */
    protected int $dispatcherPoolSize = 4;

    /**
     * @var bool Whether to collect per-actor telemetry (metrics + tracing)
     */
    protected bool $telemetryEnabled = false;

    /**
     * @var bool Whether cluster sharding (gossip membership + rebalance) is on
     */
    protected bool $clusterEnabled = false;

    /**
     * @var string Stable node id for this process in the cluster
     */
    protected string $clusterNodeId = 'local';

    /**
     * @var string Bind host advertised to peers
     */
    protected string $clusterHost = '127.0.0.1';

    /**
     * @var int Bind port advertised to peers
     */
    protected int $clusterPort = 0;

    /**
     * @var int Weighted capacity of this node (more shards the higher)
     */
    protected int $clusterWeight = 1;

    /**
     * @var int Seconds of missed heartbeat before a node is suspected
     */
    protected int $clusterSuspectAfter = 3;

    /**
     * @var int Seconds of missed heartbeat before a node is marked down
     */
    protected int $clusterDownAfter = 8;

    /**
     * @var float Seconds between membership heartbeats / failure-detection ticks
     */
    protected float $clusterHeartbeatInterval = 1.0;

    /**
     * @var string UDP bind address for gossip (host)
     */
    protected string $clusterGossipHost = '0.0.0.0';

    /**
     * @var int UDP bind port for gossip
     */
    protected int $clusterGossipPort = 0;

    /**
     * @var string UDP broadcast/multicast target ("host:port")
     */
    protected string $clusterGossipBroadcast = '239.0.0.1:49999';

    /**
     * @var array<int,string> Seed peers ("host:port") for first contact
     */
    protected array $clusterSeeds = [];

    /**
     * @var int Remote transport TCP connection pool size per node
     */
    protected int $clusterPoolSize = 16;

    /**
     * @var string Shared HMAC secret for gossip message signing (anti-spoofing)
     */
    protected string $clusterSecret = '';

    /**
     * @var int Allowed clock skew (seconds) for gossip message freshness
     */
    protected int $clusterClockSkew = 30;

    /**
     * @var string This node's private key PEM (asymmetric, per-node certificate).
     *             When set, gossip switches from shared HMAC to per-node signing.
     */
    protected string $clusterPrivateKey = '';

    /**
     * @var string This node's public key PEM (paired with clusterPrivateKey).
     */
    protected string $clusterPublicKey = '';

    /**
     * @var array<string,string> Pinned trust store: nodeId => public-key PEM.
     *             When non-empty, only nodes whose pubkey is pinned are accepted.
     */
    protected array $clusterTrustStore = [];

    /**
     * @var bool Whether actor persistence is replicated across cluster nodes
     *           (cross-node durability). Requires clusterEnabled + persistenceEnabled.
     */
    protected bool $clusterStoreEnabled = false;

    /**
     * @var int Number of replicas an actor's events/snapshots are replicated to
     *          (besides the owning node). Quorum for a read = 1 replica present.
     */
    protected int $clusterReplicationFactor = 2;


    /**
     * Build the actor config from the "actor" config key.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

	/**
	 * @return int
	 */
	public function getMaxCount(): int
	{
		return $this->maxCount;
	}

	/**
	 * @param int $maxCount
	 * @return void
	 */
	public function setMaxCount(int $maxCount): void
	{
		$this->maxCount = $maxCount;
	}

	/**
	 * @return int
	 */
	public function getMaxClassCount(): int
	{
		return $this->maxClassCount;
	}

	/**
	 * @param int $maxClassCount
	 * @return void
	 */
	public function setMaxClassCount(int $maxClassCount): void
	{
		$this->maxClassCount = $maxClassCount;
	}

	/**
	 * @return int
	 */
	public function getWorkerCount(): int
	{
		return $this->workerCount;
	}

	/**
	 * @param int $workerCount
	 * @return void
	 */
	public function setWorkerCount(int $workerCount): void
	{
		$this->workerCount = $workerCount;
	}

	/**
	 * @return int
	 */
	public function getMailboxCapacity(): int
	{
		return $this->mailboxCapacity;
	}

	/**
	 * @param int $mailboxCapacity
	 * @return void
	 */
	public function setMailboxCapacity(int $mailboxCapacity): void
	{
		$this->mailboxCapacity = $mailboxCapacity;
	}

	/**
	 * @return string
	 */
	public function getMailboxOverflow(): string
	{
		return $this->mailboxOverflow;
	}

	/**
	 * @param string $mailboxOverflow
	 * @return void
	 */
	public function setMailboxOverflow(string $mailboxOverflow): void
	{
		$this->mailboxOverflow = $mailboxOverflow;
	}

	/**
	 * @return float
	 */
	public function getMailboxPushTimeout(): float
	{
		return $this->mailboxPushTimeout;
	}

	/**
	 * @param float $mailboxPushTimeout
	 * @return void
	 */
	public function setMailboxPushTimeout(float $mailboxPushTimeout): void
	{
		$this->mailboxPushTimeout = $mailboxPushTimeout;
	}

	/**
	 * @return string
	 */
	public function getSupervisorStrategy(): string
	{
		return $this->supervisorStrategy;
	}

	/**
	 * @param string $supervisorStrategy
	 * @return void
	 */
	public function setSupervisorStrategy(string $supervisorStrategy): void
	{
		$this->supervisorStrategy = $supervisorStrategy;
	}

	/**
	 * @return int
	 */
	public function getSupervisorMaxRetries(): int
	{
		return $this->supervisorMaxRetries;
	}

	/**
	 * @param int $supervisorMaxRetries
	 * @return void
	 */
	public function setSupervisorMaxRetries(int $supervisorMaxRetries): void
	{
		$this->supervisorMaxRetries = $supervisorMaxRetries;
	}

	/**
	 * @return string
	 */
	public function getSupervisorMode(): string
	{
		return $this->supervisorMode;
	}

	/**
	 * @param string $supervisorMode
	 * @return void
	 */
	public function setSupervisorMode(string $supervisorMode): void
	{
		$this->supervisorMode = $supervisorMode;
	}

	/**
	 * @return bool
	 */
	public function isPersistenceEnabled(): bool
	{
		return $this->persistenceEnabled;
	}

	/**
	 * @param bool $persistenceEnabled
	 * @return void
	 */
	public function setPersistenceEnabled(bool $persistenceEnabled): void
	{
		$this->persistenceEnabled = $persistenceEnabled;
	}

	/**
	 * @return string
	 */
	public function getPersistenceDir(): string
	{
		return $this->persistenceDir;
	}

	/**
	 * @param string $persistenceDir
	 * @return void
	 */
	public function setPersistenceDir(string $persistenceDir): void
	{
		$this->persistenceDir = $persistenceDir;
	}

	/**
	 * @return string
	 */
	public function getRoutingStrategy(): string
	{
		return $this->routingStrategy;
	}

	/**
	 * @param string $routingStrategy
	 * @return void
	 */
	public function setRoutingStrategy(string $routingStrategy): void
	{
		$this->routingStrategy = $routingStrategy;
	}

	/**
	 * @return int
	 */
	public function getRoutingReplicas(): int
	{
		return $this->routingReplicas;
	}

	/**
	 * @param int $routingReplicas
	 * @return void
	 */
	public function setRoutingReplicas(int $routingReplicas): void
	{
		$this->routingReplicas = $routingReplicas;
	}

	/**
	 * @return string
	 */
	public function getDispatcher(): string
	{
		return $this->dispatcher;
	}

	/**
	 * @param string $dispatcher
	 * @return void
	 */
	public function setDispatcher(string $dispatcher): void
	{
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @return int
	 */
	public function getDispatcherPoolSize(): int
	{
		return $this->dispatcherPoolSize;
	}

	/**
	 * @param int $dispatcherPoolSize
	 * @return void
	 */
	public function setDispatcherPoolSize(int $dispatcherPoolSize): void
	{
		$this->dispatcherPoolSize = $dispatcherPoolSize;
	}

	/**
	 * @return bool
	 */
	public function isTelemetryEnabled(): bool
	{
		return $this->telemetryEnabled;
	}

	/**
	 * @param bool $telemetryEnabled
	 * @return void
	 */
	public function setTelemetryEnabled(bool $telemetryEnabled): void
	{
		$this->telemetryEnabled = $telemetryEnabled;
	}

	/**
	 * @return bool
	 */
	public function isClusterEnabled(): bool
	{
		return $this->clusterEnabled;
	}

	/**
	 * @param bool $clusterEnabled
	 * @return void
	 */
	public function setClusterEnabled(bool $clusterEnabled): void
	{
		$this->clusterEnabled = $clusterEnabled;
	}

	/**
	 * @return string
	 */
	public function getClusterNodeId(): string
	{
		return $this->clusterNodeId;
	}

	/**
	 * @param string $clusterNodeId
	 * @return void
	 */
	public function setClusterNodeId(string $clusterNodeId): void
	{
		$this->clusterNodeId = $clusterNodeId;
	}

	/**
	 * @return string
	 */
	public function getClusterHost(): string
	{
		return $this->clusterHost;
	}

	/**
	 * @param string $clusterHost
	 * @return void
	 */
	public function setClusterHost(string $clusterHost): void
	{
		$this->clusterHost = $clusterHost;
	}

	/**
	 * @return int
	 */
	public function getClusterPort(): int
	{
		return $this->clusterPort;
	}

	/**
	 * @param int $clusterPort
	 * @return void
	 */
	public function setClusterPort(int $clusterPort): void
	{
		$this->clusterPort = $clusterPort;
	}

	/**
	 * @return int
	 */
	public function getClusterWeight(): int
	{
		return $this->clusterWeight;
	}

	/**
	 * @param int $clusterWeight
	 * @return void
	 */
	public function setClusterWeight(int $clusterWeight): void
	{
		$this->clusterWeight = $clusterWeight;
	}

	/**
	 * @return int
	 */
	public function getClusterSuspectAfter(): int
	{
		return $this->clusterSuspectAfter;
	}

	/**
	 * @param int $clusterSuspectAfter
	 * @return void
	 */
	public function setClusterSuspectAfter(int $clusterSuspectAfter): void
	{
		$this->clusterSuspectAfter = $clusterSuspectAfter;
	}

	/**
	 * @return int
	 */
	public function getClusterDownAfter(): int
	{
		return $this->clusterDownAfter;
	}

	/**
	 * @param int $clusterDownAfter
	 * @return void
	 */
	public function setClusterDownAfter(int $clusterDownAfter): void
	{
		$this->clusterDownAfter = $clusterDownAfter;
	}

	/**
	 * @return float
	 */
	public function getClusterHeartbeatInterval(): float
	{
		return $this->clusterHeartbeatInterval;
	}

	/**
	 * @param float $clusterHeartbeatInterval
	 * @return void
	 */
	public function setClusterHeartbeatInterval(float $clusterHeartbeatInterval): void
	{
		$this->clusterHeartbeatInterval = $clusterHeartbeatInterval;
	}

	/**
	 * @return string
	 */
	public function getClusterGossipHost(): string
	{
		return $this->clusterGossipHost;
	}

	/**
	 * @param string $clusterGossipHost
	 * @return void
	 */
	public function setClusterGossipHost(string $clusterGossipHost): void
	{
		$this->clusterGossipHost = $clusterGossipHost;
	}

	/**
	 * @return int
	 */
	public function getClusterGossipPort(): int
	{
		return $this->clusterGossipPort;
	}

	/**
	 * @param int $clusterGossipPort
	 * @return void
	 */
	public function setClusterGossipPort(int $clusterGossipPort): void
	{
		$this->clusterGossipPort = $clusterGossipPort;
	}

	/**
	 * @return string
	 */
	public function getClusterGossipBroadcast(): string
	{
		return $this->clusterGossipBroadcast;
	}

	/**
	 * @param string $clusterGossipBroadcast
	 * @return void
	 */
	public function setClusterGossipBroadcast(string $clusterGossipBroadcast): void
	{
		$this->clusterGossipBroadcast = $clusterGossipBroadcast;
	}

	/**
	 * @return array<int,string>
	 */
	public function getClusterSeeds(): array
	{
		return $this->clusterSeeds;
	}

	/**
	 * @param array<int,string> $clusterSeeds
	 * @return void
	 */
	public function setClusterSeeds(array $clusterSeeds): void
	{
		$this->clusterSeeds = $clusterSeeds;
	}

	/**
	 * @return int
	 */
	public function getClusterPoolSize(): int
	{
		return $this->clusterPoolSize;
	}

	/**
	 * @param int $clusterPoolSize
	 * @return void
	 */
	public function setClusterPoolSize(int $clusterPoolSize): void
	{
		$this->clusterPoolSize = $clusterPoolSize;
	}

	/**
	 * @return string
	 */
	public function getClusterSecret(): string
	{
		return $this->clusterSecret;
	}

	/**
	 * @param string $clusterSecret
	 * @return void
	 */
	public function setClusterSecret(string $clusterSecret): void
	{
		$this->clusterSecret = $clusterSecret;
	}

	/**
	 * @return int
	 */
	public function getClusterClockSkew(): int
	{
		return $this->clusterClockSkew;
	}

	/**
	 * @param int $clusterClockSkew
	 * @return void
	 */
	public function setClusterClockSkew(int $clusterClockSkew): void
	{
		$this->clusterClockSkew = $clusterClockSkew;
	}

	/**
	 * @return string
	 */
	public function getClusterPrivateKey(): string
	{
		return $this->clusterPrivateKey;
	}

	/**
	 * @param string $clusterPrivateKey
	 * @return void
	 */
	public function setClusterPrivateKey(string $clusterPrivateKey): void
	{
		$this->clusterPrivateKey = $clusterPrivateKey;
	}

	/**
	 * @return string
	 */
	public function getClusterPublicKey(): string
	{
		return $this->clusterPublicKey;
	}

	/**
	 * @param string $clusterPublicKey
	 * @return void
	 */
	public function setClusterPublicKey(string $clusterPublicKey): void
	{
		$this->clusterPublicKey = $clusterPublicKey;
	}

	/**
	 * @return array<string,string>
	 */
	public function getClusterTrustStore(): array
	{
		return $this->clusterTrustStore;
	}

	/**
	 * @param array<string,string> $clusterTrustStore
	 * @return void
	 */
	public function setClusterTrustStore(array $clusterTrustStore): void
	{
		$this->clusterTrustStore = $clusterTrustStore;
	}

	/**
	 * @return bool
	 */
	public function isClusterStoreEnabled(): bool
	{
		return $this->clusterStoreEnabled;
	}

	/**
	 * @param bool $clusterStoreEnabled
	 * @return void
	 */
	public function setClusterStoreEnabled(bool $clusterStoreEnabled): void
	{
		$this->clusterStoreEnabled = $clusterStoreEnabled;
	}

	/**
	 * @return int
	 */
	public function getClusterReplicationFactor(): int
	{
		return $this->clusterReplicationFactor;
	}

	/**
	 * @param int $clusterReplicationFactor
	 * @return void
	 */
	public function setClusterReplicationFactor(int $clusterReplicationFactor): void
	{
		$this->clusterReplicationFactor = $clusterReplicationFactor;
	}

	/**
	 * Mirror a top-level {@see ClusterConfig} onto this actor config so legacy
	 * call-sites that read via the `getClusterXxx()` accessors keep working now
	 * that cluster settings live under the `yew.cluster` subtree instead of
	 * `yew.actor.cluster`.
	 */
	public function applyClusterCompat(ClusterConfig $cluster): void
	{
		$this->setClusterEnabled($cluster->isEnabled());
		$this->setClusterNodeId($cluster->getNodeId());
		$this->setClusterHost($cluster->getHost());
		$this->setClusterPort($cluster->getPort());
		$this->setClusterWeight($cluster->getWeight());
		$this->setClusterSuspectAfter($cluster->getSuspectAfter());
		$this->setClusterDownAfter($cluster->getDownAfter());
		$this->setClusterHeartbeatInterval($cluster->getHeartbeatInterval());
		$this->setClusterGossipHost($cluster->getGossipHost());
		$this->setClusterGossipPort($cluster->getGossipPort());
		$this->setClusterGossipBroadcast($cluster->getGossipBroadcast());
		$this->setClusterSeeds($cluster->getSeeds());
		$this->setClusterPoolSize($cluster->getPoolSize());
		$this->setClusterSecret($cluster->getSecret());
		$this->setClusterClockSkew($cluster->getClockSkew());
		$this->setClusterPrivateKey($cluster->getPrivateKey());
		$this->setClusterPublicKey($cluster->getPublicKey());
		$this->setClusterTrustStore($cluster->getTrustStore());
		$this->setClusterReplicationFactor($cluster->getReplicationFactor());
		// storeEnabled is derived from persistence + replication in the
		// ClusterPlugin; keep the legacy flag in sync for any reader.
		$this->setClusterStoreEnabled(
			$this->isPersistenceEnabled() && $cluster->getReplicationFactor() > 0
		);
	}
}