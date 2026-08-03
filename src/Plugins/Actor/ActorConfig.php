<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Plugins\Config\BaseConfig;


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
}