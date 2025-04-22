<?php
/**
 * ESD framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Pack;

/**
 * Trait GetClientData
 * @package Yew\Plugins\Pack
 */
trait GetClientData
{
    /**
     * @return ClientData|null
     */
    public function getClientData(): ?ClientData
    {
       return getDeepContextValueByClassName(ClientData::class);
    }
}