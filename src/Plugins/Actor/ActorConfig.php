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
     * @var int Actor mx class count
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
}