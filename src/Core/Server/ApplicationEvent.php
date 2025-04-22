<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server;

use Yew\Core\Plugins\Event\Event;

class ApplicationEvent extends Event
{
    const ApplicationStartingEvent = "ApplicationStartingEvent";

    const ApplicationShutdownEvent = "ApplicationShutdownEvent";
}