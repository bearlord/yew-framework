<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugin;

use Yew\Core\Channel\Channel;
use Yew\Core\Context\Context;

/**
 * Interface PluginInterface
 * @package Yew\Core\Plugin
 */
interface PluginInterface
{
    /**
     * Get ready channel
     *
     * @return Channel
     */
    public function getReadyChannel();

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * initialization
     *
     * @param Context $context
     * @return mixed
     */
    public function init(Context $context);

    /**
     * Before server start
     *
     * @param Context $context
     * @return mixed
     */
    public function beforeServerStart(Context $context);

    /**
     * Before process start
     *
     * @param Context $context
     * @return mixed
     */
    public function beforeProcessStart(Context $context);

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager);

}