<?php

namespace Yew\Plugins\Actor;

use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Event\ActorCreateEvent;
use Yew\Plugins\Actor\Routing\ActorRoutingStrategy;
use Yew\Plugins\Actor\Routing\RoundRobinStrategy;
use Yew\Plugins\Actor\Routing\ConsistentHashStrategy;
use Yew\Plugins\Actor\Routing\LeastLoadedStrategy;

class ActorFactory
{
    /**
     * Create an actor and return its IPC proxy
     *
     * @param string      $actionClass Actor class name
     * @param string      $actorName   Actor name (globally unique)
     * @param mixed       $data        Initialization data
     * @param bool        $waitCreate  Whether to wait for creation to finish
     * @param float       $timeOut     Wait timeout in seconds
     * @param string|null $parentName  Parent actor name; when set the child is
     *                                 created in the parent's process (supervision tree)
     * @param string|null $routingKey  Key for key-based routing (consistent hash);
     *                                 defaults to $actorName when null
     * @return ActorIpcProxy|false
     * @throws ActorException
     */
    public static function create(
        string $actionClass,
        string $actorName,
        $data = null,
        bool $waitCreate = true,
        float $timeOut = 5,
        ?string $parentName = null,
        ?string $routingKey = null)
    {
        if ($waitCreate && ActorManager::getInstance()->hasActor($actorName)) {
            return new ActorIpcProxy($actorName, false, $timeOut);
        }

        $processes = Server::$instance->getProcessManager()->getProcessGroup(ActorConfig::GROUP_NAME);

        $processList  = $processes->getProcesses();
        $processCount = count($processList);
        if ($processCount === 0) {
            throw new ActorException("No actor worker process available");
        }

        // When parenting, the child must live in the SAME process as the parent
        // so the supervisor can restart/stop it directly.
        if ($parentName !== null) {
            $parentInfo = ActorManager::getInstance()->getActorInfo($parentName);
            if ($parentInfo === null) {
                throw new ActorException("Parent actor does not exist: {$parentName}");
            }
            $targetProcess = $parentInfo->getProcess();
        } else {
            $index = self::selectProcessIndex($processCount, $routingKey ?? $actorName);
            $targetProcess = $processList[$index];
        }

        Server::$instance->getEventDispatcher()->dispatchProcessEvent(new ActorCreateEvent(
            ActorCreateEvent::ActorCreateEvent,
            [
                $actionClass, $actorName, $data, true, $parentName
            ]), $targetProcess);

        if (!$waitCreate) {
            return true;
        }

        $call   = Server::$instance->getEventDispatcher()->listen(ActorCreateEvent::ActorCreateReadyEvent . ":" . $actorName, null, true);
        $result = $call->wait($timeOut);
        if ($result === null) {
            return false;
        }

        return new ActorIpcProxy($actorName, false, $timeOut);
    }

    /**
     * Select the worker process index for a new actor using the configured
     * routing strategy (round-robin / consistent-hash / least-loaded).
     *
     * @param int         $processCount
     * @param string|null $routingKey   Key for key-based routing
     * @return int
     */
    protected static function selectProcessIndex(int $processCount, ?string $routingKey = null): int
    {
        $config = ActorManager::getInstance()->getActorConfig();
        $strategy = self::resolveRoutingStrategy($config->getRoutingStrategy(), $config->getRoutingReplicas());

        return $strategy->select($processCount, $routingKey);
    }

    /**
     * Build the routing strategy instance for the given name.
     *
     * @param string $name
     * @param int    $replicas
     * @return ActorRoutingStrategy
     */
    protected static function resolveRoutingStrategy(string $name, int $replicas): ActorRoutingStrategy
    {
        $manager = ActorManager::getInstance();

        switch ($name) {
            case 'consistent-hash':
                return new ConsistentHashStrategy($replicas);
            case 'least-loaded':
                return new LeastLoadedStrategy($manager);
            case 'round-robin':
            default:
                return new RoundRobinStrategy($manager);
        }
    }

    /**
     * Whether an actor with the given name already exists.
     *
     * @param string $actorName
     * @return bool
     */
    public static function has(string $actorName): bool
    {
        return ActorManager::getInstance()->hasActor($actorName);
    }

    /**
     * Get a handle to an existing Actor by name.
     *
     * @param string $actorName
     * @return Actor|ActorIpcProxy|null
     */
    public static function get(string $actorName, bool $oneWay = false, float $timeOut = 5)
    {
        return ActorManager::getInstance()->getActor($actorName, $oneWay, $timeOut);
    }
}