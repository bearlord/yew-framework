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
    protected int $actorMaxCount = 10000;

    /**
     * @var int Actor mx class count
     */
    protected int $actorMaxClassCount = 100;

    /**
     * @var int Actor worker count
     */
    protected int $actorWorkerCount = 1;

    /**
     * @var int Actor mailbox capacity
     */
    protected int $actorMailboxCapacity = 100;

    /**
     * ActorConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return int
     */
    public function getActorMaxCount(): int
    {
        return $this->actorMaxCount;
    }

    /**
     * @param int $actorMaxCount
     */
    public function setActorMaxCount(int $actorMaxCount): void
    {
        $this->actorMaxCount = $actorMaxCount;
    }

    /**
     * @return int
     */
    public function getActorMaxClassCount(): int
    {
        return $this->actorMaxClassCount;
    }

    /**
     * @param int $actorMaxClassCount
     */
    public function setActorMaxClassCount(int $actorMaxClassCount): void
    {
        $this->actorMaxClassCount = $actorMaxClassCount;
    }


    /**
     * @return int
     */
    public function getActorWorkerCount(): int
    {
        return $this->actorWorkerCount;
    }

    /**
     * @param int $actorWorkerCount
     */
    public function setActorWorkerCount(int $actorWorkerCount): void
    {
        $this->actorWorkerCount = $actorWorkerCount;
    }

    /**
     * @return int
     */
    public function getActorMailboxCapacity(): int
    {
        return $this->actorMailboxCapacity;
    }

    /**
     * @param int $actorMailboxCapacity
     */
    public function setActorMailboxCapacity(int $actorMailboxCapacity): void
    {
        $this->actorMailboxCapacity = $actorMailboxCapacity;
    }
}