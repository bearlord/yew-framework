<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Memory\CrossProcess\Atomic;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Yew;

class ActorManager
{
    use GetLogger;
    /**
     * @var ActorManager
     */
    protected static $instance;

    /**
     * @var Table
     */
    protected $actorTable;

    /**
     * @var Table
     */
    protected $actorIdClassNameTable;

    /**
     * @var Table
     */
    protected $actorClassNameIdTable;

    /**
     *
     * @var int
     */
    protected $serverStartTime;

    /**
     * @var ActorConfig
     */
    protected $actorConfig;

    /**
     * @var Atomic
     */
    protected $atomic;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->actorConfig = DIGet(ActorConfig::class);
        $this->actorTable = new Table($this->actorConfig->getMaxCount());
        $this->actorTable->column("processId", Table::TYPE_INT);
        $this->actorTable->column("createTime", Table::TYPE_INT);
        $this->actorTable->column("classId", Table::TYPE_INT);
        $this->actorTable->create();

        $this->actorIdClassNameTable = new Table($this->actorConfig->getMaxClassCount());
        $this->actorIdClassNameTable->column("className", Table::TYPE_STRING, 100);
        $this->actorIdClassNameTable->create();

        $this->actorClassNameIdTable = new Table($this->actorConfig->getMaxClassCount());
        $this->actorClassNameIdTable->column("id", Table::TYPE_INT);
        $this->actorClassNameIdTable->create();

        $this->atomic = new Atomic();
    }

    /**
     * @return ActorManager
     * @throws \Exception
     */
    public static function getInstance(): ActorManager
    {
        if (self::$instance === null) {
            self::$instance = new ActorManager();
        }
        return self::$instance;
    }

    /**
     * @param string $actorName
     * @return ActorInfo
     */
    public function getActorInfo(string $actorName): ?ActorInfo
    {
        $data = $this->actorTable->get($actorName);
        if (empty($data)) {
            return null;
        }

        $className = $this->actorIdClassNameTable->get($data["classId"], "className");
        $actorInfo = new ActorInfo();
        $actorInfo->setName($actorName);
        $actorInfo->setClassName($className);
        $actorInfo->setProcess(Server::$instance->getProcessManager()->getProcessFromId($data["processId"]));
        $actorInfo->setCreateTime($data["createTime"]);
        return $actorInfo;
    }

    /**
     * @param Actor $actor
     * @throws ActorException
     */
    public function addActor(Actor $actor)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getGroupName() != ActorConfig::GROUP_NAME) {
            throw new ActorException("Do not new a actor, use Actor::create()");
        }

        $actorName = $actor->getName();

        if ($this->actorTable->exist($actorName)) {
            throw new ActorException("Has same actor name :{$actorName}");
        }

        $className = get_class($actor);
        $actorClassNameId = $this->actorClassNameIdTable->get($className);
        if (empty($actorClassNameId)) {
            $id = $this->actorIdClassNameTable->count();
            $this->actorIdClassNameTable->set($id, ["className" => $className]);
            $this->actorClassNameIdTable->set($className, ["id" => $id]);
        } else {
            $id = $actorClassNameId["id"];
        }

        $this->actorTable->set($actorName, [
            "processId" => Server::$instance->getProcessManager()->getCurrentProcessId(),
            "createTime" => time(),
            "classId" => $id
        ]);
        DISet($className . ":" . $actorName, $actor);

        $this->debug(sprintf("Actor %s created", $actor->getName()));
    }

    /**
     * @param Actor $actor
     */
    public function removeActor(Actor $actor)
    {
        $actorName = $actor->getName();

        $className = get_class($actor);

        DISet($className . ":" . $actorName, null);
        $this->actorTable->del($actorName);

        // Note: the Actor persistent cache process (ActorCacheProcess) is not implemented yet,
        // so the delete event is not dispatched for now.
        // If cross-process cache cleanup is needed, dispatch ActorDeleteEvent to the relevant process here.

        $this->debug(sprintf("Actor %s removed", $actorName));
    }

    /**
     * @return Atomic
     */
    public function getAtomic(): Atomic
    {
        return $this->atomic;
    }

    /**
     * @param string $actorName
     * @return bool
     */
    public function hasActor(string $actorName)
    {
        $data = $this->actorTable->get($actorName);
        if (empty($data)) {
            return false;
        }

        return true;
    }
}
