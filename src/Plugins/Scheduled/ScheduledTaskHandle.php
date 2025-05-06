<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Scheduled\Beans\ScheduledTask;
use Yew\Plugins\Scheduled\Event\ScheduledExecuteEvent;
use Yew\Coroutine\Server\Server;
use Yew\Yew;

class ScheduledTaskHandle
{
    use GetLogger;

    public function __construct()
    {
        //Listen the execution of task events
        goWithContext(function () {
            $call = Server::$instance->getEventDispatcher()->listen(ScheduledExecuteEvent::SCHEDULED_EXECUTE_EVENT);
            $call->call(function (ScheduledExecuteEvent $event){
                goWithContext(function () use ($event) {
                    $this->execute($event->getTask());
                });
            });
        });
    }

    /**
     * Execute scheduled task
     *
     * @param ScheduledTask $scheduledTask
     * @throws \Exception
     */
    public function execute(ScheduledTask $scheduledTask)
    {
        $className = $scheduledTask->getClassName();
        $taskInstance = Server::$instance->getContainer()->get($className);
        call_user_func([$taskInstance, $scheduledTask->getFunctionName()]);
        $this->debug(Yii::t('esd', 'Execute scheduled task {name}', [
            'name' => $scheduledTask->getName()
        ]));
    }
}