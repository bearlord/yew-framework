<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Process;

use Yew\Core\Plugins\Event\Event;

/**
 * Class ProcessEvent
 * @package Yew\Core\Server\Process
 */
class ProcessEvent extends Event
{
    /**
     * Process start event
     */
    const ProcessStartEvent = "ProcessStartEvent";

    /**
     * Process stop event
     */
    const ProcessStopEvent = "ProcessStopEvent";
}