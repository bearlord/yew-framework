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
     * @return string|null
     */
    public function getControllerName(): ?string;

    /**
     * Get method name
     *
     * @return string|null
     */
    public function getMethodName(): ?string;

    /**
     * Get params
     *
     * @return string|null
     */
    public function getParams(): ?array;

    /**
     * Get path
     *
     * @return string|null
     */
    public function getPath(): ?string;
}