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
use Yew\Plugins\Actor\Cluster\ClusterNode;
use Yew\Plugins\Actor\Cluster\Location;
use Yew\Plugins\Actor\Cluster\ShardRouter;
use Yew\Plugins\Actor\Cluster\LocalShardRouter;
use Yew\Plugins\Actor\Cluster\RemoteTransport;
use Yew\Plugins\Actor\Cluster\LocalTransport;
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
     * Per worker-process actor load counter (index => count), used by the
     * least-loaded routing strategy. Backed by shared memory (Table).
     *
     * @var Table
     */
    protected $loadTable;

    /**
     * Shard router: resolves actor name -> physical location. The clustering
     * seam; defaults to {@see LocalShardRouter} in single-machine mode.
     *
     * @var ShardRouter
     */
    protected ShardRouter $shardRouter;

    /**
     * Remote transport for cross-node delivery. No-op for local deployment.
     *
     * @var RemoteTransport
     */
    protected RemoteTransport $remoteTransport;

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

        $this->loadTable = new Table(max($this->actorConfig->getWorkerCount(), 1));
        $this->loadTable->column("load", Table::TYPE_INT);
        $this->loadTable->create();

        // Clustering seam: location-transparent addressing defaults to the
        // single-machine implementation. Swap for a gossip-based router later.
        $this->shardRouter = new LocalShardRouter('local');
        $this->remoteTransport = new LocalTransport();
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
     * Raw shared-memory row for an actor (used by the shard router to build a
     * {@see \Yew\Plugins\Actor\Cluster\Location} without constructing ActorInfo).
     *
     * @param string $actorName
     * @return array|null
     */
    public function getActorRaw(string $actorName): ?array
    {
        $data = $this->actorTable->get($actorName);

        return empty($data) ? null : $data;
    }

    /**
     * @return ShardRouter
     */
    public function getShardRouter(): ShardRouter
    {
        return $this->shardRouter;
    }

    /**
     * Replace the shard router (e.g. with a clustered/gossip implementation).
     *
     * @param ShardRouter $shardRouter
     */
    public function setShardRouter(ShardRouter $shardRouter): void
    {
        $this->shardRouter = $shardRouter;
    }

    /**
     * @return RemoteTransport
     */
    public function getRemoteTransport(): RemoteTransport
    {
        return $this->remoteTransport;
    }

    /**
     * Replace the remote transport (e.g. with a real network implementation).
     *
     * @param RemoteTransport $remoteTransport
     */
    public function setRemoteTransport(RemoteTransport $remoteTransport): void
    {
        $this->remoteTransport = $remoteTransport;
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
            throw new ActorException("Do not new a actor, use ActorSystem::create()");
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

        $currentProcessId = Server::$instance->getProcessManager()->getCurrentProcessId();
        $this->actorTable->set($actorName, [
            "processId" => $currentProcessId,
            "createTime" => time(),
            "classId" => $id,
            "parent" => $parentName ?? ""
        ]);
        DISet($className . ":" . $actorName, $actor);

        $this->incrLoad($this->indexOfProcess($currentProcessId));

        // Clustering seam: publish the actor's location (derived from ring for
        // the gossip router; no-op for the local router).
        $node = method_exists($this->shardRouter, 'getLocalNode')
            ? $this->shardRouter->getLocalNode()
            : new ClusterNode('local');
        $this->shardRouter->register($actorName, new Location($node, $currentProcessId));

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
        $row = $this->actorTable->get($actorName);
        $this->decrLoad($this->indexOfProcess((int) ($row["processId"] ?? 0)));
        $this->shardRouter->unregister($actorName);
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
        if ($old instanceof Actor) {
            $old->preRestart();
        }
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
     * @return ActorConfig
     */
    public function getActorConfig(): ActorConfig
    {
        return $this->actorConfig;
    }

    /**
     * Increment the load counter for a worker process (called on actor creation).
     *
     * @param int $processIndex
     */
    public function incrLoad(int $processIndex): void
    {
        $row = $this->loadTable->get($processIndex);
        $load = $row === false ? 0 : (int) $row["load"];
        $this->loadTable->set($processIndex, ["load" => $load + 1]);
    }

    /**
     * Decrement the load counter for a worker process (called on actor removal).
     *
     * @param int $processIndex
     */
    public function decrLoad(int $processIndex): void
    {
        $row = $this->loadTable->get($processIndex);
        $load = $row === false ? 0 : (int) $row["load"];
        $this->loadTable->set($processIndex, ["load" => max(0, $load - 1)]);
    }

    /**
     * Current actor count hosted by a worker process.
     *
     * @param int $processIndex
     * @return int
     */
    public function getLoad(int $processIndex): int
    {
        $row = $this->loadTable->get($processIndex);

        return $row === false ? 0 : (int) $row["load"];
    }

    /**
     * Resolve a process id to its index inside the actor process group.
     *
     * @param int $processId
     * @return int Index in [0, processCount), defaults to 0 when not found
     */
    public function indexOfProcess(int $processId): int
    {
        $group = Server::$instance->getProcessManager()->getProcessGroup(ActorConfig::GROUP_NAME);
        if ($group === null) {
            return 0;
        }

        foreach ($group->getProcesses() as $index => $process) {
            if ($process->getProcessId() === $processId) {
                return $index;
            }
        }

        return 0;
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
     * Names of actors whose real instance lives in THIS process (not proxies).
     *
     * Used by cluster rebalancing to find local actors that must be evicted
     * when the consistent-hash ring no longer maps them to this node.
     *
     * @return string[]
     */
    public function getLocalActorNames(): array
    {
        $current = Server::$instance->getProcessManager()->getCurrentProcessId();
        $names = [];
        foreach ($this->actorTable as $name => $row) {
            if ((int)($row['processId'] ?? -1) === $current) {
                $names[] = $name;
            }
        }
        return $names;
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
        // Use getProxy() so a missing/ unresolvable actor yields false instead
        // of an uncaught exception (the proxy constructor throws ActorException
        // when the actor's location or info cannot be resolved).
        return Actor::getProxy($actorName, $oneWay, $timeOut);
    }
}
