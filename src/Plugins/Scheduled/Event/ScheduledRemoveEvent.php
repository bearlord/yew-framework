<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled\Event;

use Yew\Core\Plugins\Event\Event;
use Yew\Plugins\Scheduled\Beans\ScheduledTask;

class ScheduledRemoveEvent extends Event
{
    const SCHEDULED_REMOVE_EVENT = "ScheduledRemoveEvent";

    /**
     * ScheduledRemoveEvent constructor.
     * @param string $scheduledTaskName
     */
    public function __construct(string $scheduledTaskName)
    {
        parent::__construct(self::SCHEDULED_REMOVE_EVENT, $scheduledTaskName);
    }

    /**
     * @return ScheduledTask
     */
    public function getTaskName(): string
    {
        return $this->getData();
    }
}