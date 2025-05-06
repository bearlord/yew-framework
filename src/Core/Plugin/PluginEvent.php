<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugin;

use Yew\Core\Plugins\Event\Event;

class PluginEvent extends Event
{
    /**
     * Plugin success event
     */
    const PluginSuccessEvent = "PluginSuccessEvent";

    /**
     * Plugin fail event
     */
    const PlugFailEvent = "PlugFailEvent";

    /**
     * Plugin ready
     */
    const PlugReady = "PlugReady";
}