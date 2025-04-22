<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Config;

use Yew\Core\Plugins\Event\Event;

/**
 * Class ConfigChangeEvent
 * @package Yew\Core\Plugins\Config
 */
class ConfigChangeEvent extends Event
{
    const ConfigChangeEvent = "ConfigChangeEvent";

    /**
     * ConfigChangeEvent constructor.
     */
    public function __construct()
    {
        parent::__construct(self::ConfigChangeEvent, null);
    }
}