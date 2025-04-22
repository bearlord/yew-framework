<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugin;

use Yew\Core\Plugins\Event\Event;

/**
 * Class PluginManagerEvent
 * @package Yew\Core\Plugin
 */
class PluginManagerEvent extends Event
{
    /**
     * Plugin before server start event
     */
    const PlugBeforeServerStartEvent = "PlugBeforeServerStartEvent";

    /**
     * Plugin after server start event
     */
    const PlugAfterServerStartEvent = "PlugAfterServerStartEvent";

    /**
     * Plugin before process start event
     */
    const PlugBeforeProcessStartEvent = "PlugBeforeProcessStartEvent";

    /**
     * Plugin after process start event
     */
    const PlugAfterProcessStartEvent = "PlugAfterProcessStartEvent";

    /**
     * Plugin all ready event
     */
    const PlugAllReadyEvent = "PlugAllReadyEvent";
}