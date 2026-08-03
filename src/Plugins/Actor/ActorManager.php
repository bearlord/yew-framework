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
     * Parent -> children relationship table (children names joined by ",")
     *
     * @var Table
     */
    protected $actorChildrenTable;

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
        $this->actorTable  = new Table($this->actorConfig->getMaxCount());
        $this->actorTable->column("processId", Table::TYPE_INT);
        $this->actorTable->column("createTime", Table::TYPE_INT);
        $this->actorTable->column("classId", Table::TYPE_INT);
        $this->actorTable->column("parent", Table::TYPE_STRING, 100);
        $this->actorTable->create();

        $this->actorChildrenTable = new Table($this->actorConfig->getMaxCount());
        $this->actorChildrenTable->column("children", Table::TYPE_STRING, 4 * 1024);
        $this->actorChildrenTable->create();

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
     * Register an actor and link it under its parent (if any).
     *
     * @param Actor       $actor
     * @param string|null $parentName Parent actor name, for supervision tree
     * @throws ActorException
     */
    public function addActor(Actor $actor, ?string $parentName = null)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getGroupName() != ActorConfig::GROUP_NAME) {
            throw new ActorException("Do not new a actor, use ActorFactory::create()");
        }

        $actorName = $actor->getName();

        if ($this->actorTable->exist($actorName)) {
            throw new ActorException("Has same actor name :{$actorName}");
        }

        // Parent must live in the same process group (supervision tree is intra-process).
        if ($parentName !== null && !$this->actorTable->exist($parentName)) {
            throw new ActorException("Parent actor does not exist: {$parentName}");
        }

        $className        = get_class($actor);
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
            "classId" => $id,
            "parent" => $parentName ?? ""
        ]);
        DISet($className . ":" . $actorName, $actor);

        if ($parentName !== null) {
            $this->addChild($parentName, $actorName);
            $actor->setParentName($parentName);
        }
    }

    /**
     * Record a child under its parent.
     *
     * @param string $parentName
     * @param string $childName
     */
    private function addChild(string $parentName, string $childName): void
    {
        $row = $this->actorChildrenTable->get($parentName);
        $children = $row === false ? [] : explode(",", $row["children"]);
        $children[] = $childName;
        $this->actorChildrenTable->set($parentName, ["children" => implode(",", array_unique($children))]);
    }

    /**
     * Get the parent actor name, or null when this is a root actor.
     *
     * @param string $actorName
     * @return string|null
     */
    public function getParent(string $actorName): ?string
    {
        $data = $this->actorTable->get($actorName);
        if (empty($data) || empty($data["parent"])) {
            return null;
        }

        return $data["parent"];
    }

    /**
     * Get the names of all direct children of the given actor.
     *
     * @param string $actorName
     * @return string[]
     */
    public function getChildren(string $actorName): array
    {
        $row = $this->actorChildrenTable->get($actorName);
        if ($row === false || $row["children"] === "") {
            return [];
        }

        return explode(",", $row["children"]);
    }

    /**
     * @param Actor $actor
     */
    public function removeActor(Actor $actor)
    {
        $actorName = $actor->getName();
        $parentName = $this->getParent($actorName);

        // Detach from parent's children list.
        if ($parentName !== null) {
            $row = $this->actorChildrenTable->get($parentName);
            if ($row !== false) {
                $children = array_diff(explode(",", $row["children"]), [$actorName]);
                if (empty($children)) {
                    $this->actorChildrenTable->del($parentName);
                } else {
                    $this->actorChildrenTable->set($parentName, ["children" => implode(",", $children)]);
                }
            }
        }

        // Cascade: stop all children when a parent is removed (Akka semantics).
        foreach ($this->getChildren($actorName) as $childName) {
            /** @var Actor|null $child */
            $child = $this->getActor($childName);
            if ($child instanceof Actor) {
                $child->destroy();
            }
        }
        $this->actorChildrenTable->del($actorName);

        $className = get_class($actor);

        DISet($className . ":" . $actorName, null);
        $this->actorTable->del($actorName);
    }

    /**
     * Recreate an existing actor in place (used by supervision Restart).
     *
     * Preserves identity (name), persistent data, and parent linkage.
     *
     * @param string $actorName
     * @return Actor|null The new actor instance, or null on failure
     */
    public function restartActor(string $actorName): ?Actor
    {
        $data = $this->actorTable->get($actorName);
        if (empty($data)) {
            return null;
        }

        $className = $this->actorIdClassNameTable->get($data["classId"], "className");
        $parentName = empty($data["parent"]) ? null : $data["parent"];

        /** @var Actor|null $old */
        $old = DIGet($className . ":" . $actorName);
        $actorData = $old instanceof Actor ? $old->getData() : [];

        // Tear down the old instance without cascading to children.
        DISet($className . ":" . $actorName, null);
        $this->actorTable->del($actorName);

        /** @var Actor $actor */
        $actor = new $className($actorName, true);
        $actor->initData($actorData);
        if ($parentName !== null) {
            $actor->setParentName($parentName);
        }

        return $actor;
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

    /**
     * Get a handle to an existing Actor by name.
     *
     * Returns null if no such actor exists.
     *
     * @param string     $actorName
     * @param bool|null  $oneWay  Whether the proxy call is one-way (no reply expected)
     * @param float|null $timeOut IPC wait timeout in seconds
     * @return Actor|ActorIpcProxy|null
     */
    public function getActor(string $actorName, ?bool $oneWay = false, ?float $timeOut = 0)
    {
        if (!$this->hasActor($actorName)) {
            return null;
        }

        // Only resolve the real instance when THIS process is the one that owns the actor.
        $data = $this->actorTable->get($actorName);
        if ((int)$data["processId"] === Server::$instance->getProcessManager()->getCurrentProcessId()) {
            $className = $this->actorIdClassNameTable->get($data["classId"], "className");

            /** @var Actor|null $actor */
            $actor = DIGet($className . ":" . $actorName);

            return $actor;
        }

        // From a worker: return an IPC proxy to the actor process.
        return new ActorIpcProxy($actorName, $oneWay, $timeOut);
    }
}
