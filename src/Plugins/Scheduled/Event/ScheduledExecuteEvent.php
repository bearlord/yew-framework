<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled\Event;

use Yew\Core\Plugins\Event\Event;
use Yew\Plugins\Scheduled\Beans\ScheduledTask;

/**
 * Class ScheduledExecuteEvent
 * @package Yew\Plugins\Scheduled\Event
 */
class ScheduledExecuteEvent extends Event
{
    const SCHEDULED_EXECUTE_EVENT = "ScheduledExecuteEvent";

    /**
     * ScheduledExecuteEvent constructor.
     * @param ScheduledTask $data
     */
    public function __construct(ScheduledTask $data)
    {
        parent::__construct(self::SCHEDULED_EXECUTE_EVENT, $data);
    }

    /**
     * @return ScheduledTask
     */
    public function getTask(): ScheduledTask
    {
        return $this->getData();
    }
}