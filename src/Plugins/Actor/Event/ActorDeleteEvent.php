<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Event;

use Yew\Core\Plugins\Event\Event;

class ActorDeleteEvent extends Event
{
    const ActorDeleteEvent = "ActorDeleteEvent";

    /**
     * @param string $type
     * @param $data
     */
    public function __construct(string $type, $data)
    {
        parent::__construct($type, $data);
    }
}
