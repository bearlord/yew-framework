<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Event\Event;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\AnnotationsScan\AnnotationsScanPlugin;
use Yew\Plugins\AnnotationsScan\ScanClass;
use Yew\Plugins\Scheduled\Annotation\Scheduled;
use Yew\Plugins\Scheduled\Beans\ScheduledTask;
use Yew\Plugins\Scheduled\Event\ScheduledAddEvent;
use Yew\Plugins\Scheduled\Event\ScheduledExecuteEvent;
use Yew\Plugins\Scheduled\Event\ScheduledRemoveEvent;
use Yew\Coroutine\Server\Server;


class ScheduledPlugin extends AbstractPlugin
{
    use GetLogger;

    const PROCESS_NAME = "helper";
    const PROCESS_GROUP_NAME = "HelperGroup";

    /**
     * @var ScheduledConfig|null
     */
    private ?ScheduledConfig $scheduledConfig;

    /**
     * Process scheduled count
     * @var array
     */
    private array $processScheduledCount = [];

    /**
     * @param ScheduledConfig|null $scheduledConfig
     */
    public function __construct(ScheduledConfig $scheduledConfig = null)
    {
        parent::__construct();
        if ($scheduledConfig == null) {
            $scheduledConfig = new ScheduledConfig();
        }
        $this->scheduledConfig = $scheduledConfig;
        $this->atAfter(AnnotationsScanPlugin::class);
    }


    /**
     * @return ScheduledConfig
     */
    public function getScheduledConfig(): ScheduledConfig
    {
        return $this->scheduledConfig;
    }

    /**
     * @param ScheduledConfig $scheduledConfig
     */
    public function setScheduledConfig(ScheduledConfig $scheduledConfig): void
    {
        $this->scheduledConfig = $scheduledConfig;
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AnnotationsScanPlugin());
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Scheduled";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function beforeServerStart(Context $context)
    {
        //Add helper process
        $this->scheduledConfig->merge();
        Server::$instance->addProcess(self::PROCESS_NAME, HelperScheduledProcess::class, self::PROCESS_GROUP_NAME);
        //Add scheduled process
        for ($i = 0; $i < $this->scheduledConfig->getTaskProcessCount(); $i++) {
            Server::$instance->addProcess("scheduled-$i", ScheduledProcess::class, ScheduledTask::GROUP_NAME);
        }
    }

    /**
     * @param Context $context
     * @return void
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        new ScheduledTaskHandle();

        //Help process
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() === self::PROCESS_NAME) {
            //Scan annotation
            $scanClass = Server::$instance->getContainer()->get(ScanClass::class);
            $reflectionMethods = $scanClass->findMethodsByAnnotation(Scheduled::class);
            foreach ($reflectionMethods as $reflectionMethod) {
                $reflectionClass = $reflectionMethod->getParentReflectClass();
                $scheduled = $scanClass->getCachedReader()->getMethodAnnotation($reflectionMethod->getReflectionMethod(), Scheduled::class);
                if ($scheduled instanceof Scheduled) {
                    if (empty($scheduled->name)) {
                        $scheduled->name = $reflectionClass->getName() . "::" . $reflectionMethod->getName();
                    }
                    if (empty($scheduled->cron)) {
                        $this->warn(sprintf("The %s task is not set to cron and has been ignored", $scheduled->name));
                        continue;
                    }
                    $scheduledTask = new ScheduledTask(
                        $scheduled->name,
                        $scheduled->cron,
                        $reflectionClass->getName(),
                        $reflectionMethod->getName(),
                        $scheduled->processGroup);
                    $this->scheduledConfig->addScheduled($scheduledTask);
                }
            }

            //Initialize the counter
            foreach (Server::$instance->getProcessManager()->getProcesses() as $process) {
                $this->processScheduledCount[$process->getProcessId()] = 0;
            }

            //Listen to dynamically added/removed task events
            goWithContext(function () {
                $call = Server::$instance->getEventDispatcher()->listen(ScheduledAddEvent::SCHEDULED_ADD_EVENT);
                Server::$instance->getEventDispatcher()->listen(ScheduledRemoveEvent::SCHEDULED_REMOVE_EVENT, $call);
                $call->call(function (Event $event) {
                    if ($event instanceof ScheduledAddEvent) {
                        $this->scheduledConfig->addScheduled($event->getTask());
                    } else if ($event instanceof ScheduledRemoveEvent) {
                        $this->scheduledConfig->removeScheduled($event->getTaskName());
                    }
                });
            });

            //Add timer scheduled task
            addTimerTick($this->scheduledConfig->getMinIntervalTime(), function () {
                foreach ($this->scheduledConfig->getScheduledTasks() as $scheduledTask) {
                    if ($scheduledTask->getCron()->isDue()) {
                        //Sort by the number of executions from small to large
                        asort($this->processScheduledCount);
                        $process = null;
                        foreach ($this->processScheduledCount as $id => $value) {
                            if ($scheduledTask->getProcessGroup() == ScheduledTask::PROCESS_GROUP_ALL) {
                                $process = Server::$instance->getProcessManager()->getProcessFromId($id);
                                break;
                            } else if (Server::$instance->getProcessManager()->getProcessFromId($id)->getGroupName() == $scheduledTask->getProcessGroup()) {
                                $process = Server::$instance->getProcessManager()->getProcessFromId($id);
                                break;
                            }
                        }
                        if ($process != null) {
                            $this->processScheduledCount[$process->getProcessId()]++;
                            Server::$instance->getEventDispatcher()->dispatchProcessEvent(new ScheduledExecuteEvent($scheduledTask), $process);
                        } else {
                            $this->warn(sprintf("The %s task did not find a scheduled process", $scheduledTask->getName()));
                        }
                    }
                }
            });
        }
        $this->ready();
    }
}