<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\RouteTool;

use Yew\Plugins\Route\RoutePortConfig;
use Yew\Plugins\Pack\ClientData;

interface IRoute
{
    /**
     * @param ClientData $clientData
     * @param RoutePortConfig $RoutePortConfig
     * @return bool
     */
    public function handleClientData(ClientData $clientData, RoutePortConfig $RoutePortConfig): bool;

    /**
     * Get Controller name
     *
     * @return mixed
     */
    public function getControllerName();

    /**
     * Get method name
     *
     * @return mixed
     */
    public function getMethodName();

    /**
     * Get params
     *
     * @return mixed
     */
    public function getParams();

    /**
     * Get path
     *
     * @return mixed
     */
    public function getPath();
}