<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Aop;

use Yew\Core\Plugins\Event\Event;

class AopEvent extends Event
{
    const TYPE = "AopEvent";

    /**
     * AopEvent constructor.
     */
    public function __construct()
    {
        parent::__construct(self::TYPE, "");
    }
}