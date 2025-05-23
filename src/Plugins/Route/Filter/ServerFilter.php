<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Filter;

use Yew\Plugins\Pack\ClientData;
use Yew\Coroutine\Server\Server;

class ServerFilter extends AbstractFilter
{
    /**
     * @return string
     */
    public function getType()
    {
        return AbstractFilter::FILTER_PRE;
    }

    /**
     * @param ClientData $clientData
     * @return int
     */
    public function filter(ClientData $clientData): int
    {
        $clientData->getResponse()->withHeader('Server', Server::$instance->getServerConfig()->getName());
        return AbstractFilter::RETURN_NEXT;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "ServerFilter";
    }

    /**
     * @param ClientData $clientData
     * @return bool
     */
    public function isEnable(ClientData $clientData)
    {
        return $this->isHttp($clientData);
    }
}