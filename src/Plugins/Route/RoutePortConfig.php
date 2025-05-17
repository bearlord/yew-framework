<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route;

use Yew\Core\Server\Config\PortConfig;
use Yew\Plugins\Route\RouteTool\AnnotationRoute;
use Yew\Plugins\Pack\PackTool\LenJsonPack;

class RoutePortConfig extends PortConfig
{
    /**
     * @var string
     */
    protected string $packTool = LenJsonPack::class;

    /**
     * @var string
     */
    protected string $routeTool = AnnotationRoute::class;


    /**
     * @return string
     */
    public function getPackTool(): string
    {
        return $this->packTool;
    }

    /**
     * @param string $packTool
     */
    public function setPackTool(string $packTool): void
    {
        $this->packTool = $packTool;
    }

    /**
     * @return string
     */
    public function getRouteTool(): string
    {
        return $this->routeTool;
    }

    /**
     * @param string $routeTool
     */
    public function setRouteTool(string $routeTool): void
    {
        $this->routeTool = $routeTool;
    }
}