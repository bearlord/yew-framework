<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack;

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