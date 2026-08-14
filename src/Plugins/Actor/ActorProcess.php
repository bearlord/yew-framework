<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Carbon\Carbon;
use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Event\ActorCreateEvent;
use Yew\Plugins\Actor\Event\ActorDestroyEvent;
use Yew\Cluster\ClusterConfig;
use Yew\Cluster\State\ClusterNode;
use Yew\Cluster\Router\IpcShardRouter;
use Yew\Cluster\Persistence\IpcReplicaTransport;
use Yew\Plugins\Actor\ActorFailover;
use Yew\Plugins\Actor\ActorConfig;
use Yew\Plugins\Actor\Persistence\ClusterActorStore;
use Yew\Plugins\Actor\Persistence\FileActorStore;
use Yew\Yew;

class ActorProcess extends Process
{

    /**
     * @return void
     */
    public function init()
    {

    }

    /**
     * @return void
     * @throws \Exception
     */
    public function onProcessStart()
    {
        $call = $this->eventDispatcher->listen(ActorCreateEvent::ActorCreateEvent);
        $call->call(function (ActorCreateEvent $event) {
            $_data = $event->getData();

            $class      = $_data[0];
            $name       = $_data[1];
            $data       = $_data[2] ?? null;
            $isCreated  = $_data[3] ?? false;
            $parentName = $_data[4] ?? null;
            $actor      = new $class($name, $isCreated, $parentName);

            if ($actor instanceof Actor) {
                $actor->initData($data);
            } else {
                throw new ActorException(sprintf("%s is not a actor", $class));
            }

            $this->eventDispatcher->dispatchProcessEvent(new ActorCreateEvent(ActorCreateEvent::ActorCreateReadyEvent . ":" . $actor->getName(), null),
                Server::$instance->getProcessManager()->getProcessFromId($event->getProcessId())
            );

        });

        // Handle cross-process destroy requests: tear down the actor inside the
        // actor process, then notify the caller that it is gone.
        $call = $this->eventDispatcher->listen(ActorDestroyEvent::ActorDestroyEvent);
        $call->call(function (ActorDestroyEvent $event) {
            $actorName = $event->getData();
            if (!is_string($actorName) || $actorName === '') {
                return;
            }

            $actor = ActorManager::getInstance()->getActor($actorName);
            if ($actor instanceof Actor) {
                $actor->destroy();
            }
            $this->eventDispatcher->dispatchProcessEvent(
                new ActorDestroyEvent(ActorDestroyEvent::ActorDestroyReadyEvent . ":" . $actorName, null),
                Server::$instance->getProcessManager()->getProcessFromId($event->getProcessId())
            );
        });

        Server::$instance->getProcessTable()->set($this->processName, [
            "process_name" => $this->processName,
            "status" => 0,
            "init_time" => (Carbon::now())->format("Y-m-d H:i:s.u"),
            "ready_time" => null,
            "last_exit_time" => null
        ]);

        // After a process restart the shared actorTable may still hold rows that
        // belong to this process but have no live in-process instance. Re-create
        // them (and replay durable state via recovery()) so proxies dispatch to a
        // live actor instead of a dead process.
        try {
            $recovered = ActorManager::getInstance()->recoverLocalActors();
            if (!empty($recovered)) {
                Server::$instance->getLog()->info(sprintf(
                    'ActorProcess %s recovered %d actor(s) on startup: %s',
                    $this->processName,
                    count($recovered),
                    implode(', ', $recovered)
                ));
            }
        } catch (\Throwable $e) {
            Server::$instance->getLog()->warning(sprintf(
                'ActorProcess %s recoverLocalActors failed: %s',
                $this->processName,
                $e->getMessage()
            ));
        }

        // Cross-node failover: only the first actor process drives resurrection
        // so we never double-spawn the same actor across sibling actor processes.
        // Replicas live solely in the cluster-state process; we read them over IPC
        // and re-create the actors the ring now assigns to this node.
        if ($this->processName === 'actor-0') {
            try {
                $actorConfig = DIGet(ActorConfig::class);
                $clusterConfig = DIGet(ClusterConfig::class);
                $store = new ClusterActorStore(
                    new FileActorStore($actorConfig->getPersistenceDir())
                );
                $store->setCluster(new IpcReplicaTransport());
                $localNode = new ClusterNode(
                    $clusterConfig->getNodeId(),
                    $clusterConfig->getHost(),
                    $clusterConfig->getPort(),
                    true
                );
                $router = new IpcShardRouter($localNode, $clusterConfig->getReplicas());
                $failover = new ActorFailover($router, $store, $this->processName);
                \Swoole\Timer::tick(2000, static function () use ($failover) {
                    try {
                        $failover->run();
                    } catch (\Throwable $e) {
                        Server::$instance->getLog()->warning(
                            'ActorProcess failover sweep failed: ' . $e->getMessage()
                        );
                    }
                });
            } catch (\Throwable $e) {
                Server::$instance->getLog()->warning(sprintf(
                    'ActorProcess %s failover init failed: %s',
                    $this->processName,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * @return void
     */
    public function onProcessStop()
    {

    }

    /**
     * @param Message $message
     * @param Process $fromProcess
     * @return void
     */
    public function onPipeMessage(Message $message, Process $fromProcess)
    {

    }
}
