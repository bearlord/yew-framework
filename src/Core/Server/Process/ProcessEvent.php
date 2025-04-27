<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Process;

use Yew\Core\Plugins\Event\Event;

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