<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled\Event;

use Yew\Core\Plugins\Event\Event;
use Yew\Plugins\Scheduled\Beans\ScheduledTask;

class ScheduledAddEvent extends Event
{
    const SCHEDULED_ADD_EVENT = "ScheduledAddEvent";

    /**
     * ScheduledAddEvent constructor.
     * @param ScheduledTask $data
     */
    public function __construct(ScheduledTask $data)
    {
        parent::__construct(self::SCHEDULED_ADD_EVENT, $data);
    }

    /**
     * @return ScheduledTask
     */
    public function getTask(): ScheduledTask
    {
        return $this->getData();
    }
}