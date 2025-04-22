<?php
/**
 * ESD framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Route\RouteTool;

use Yew\Plugins\Route\Controller\RouteController;
use Yew\Plugins\Route\RouteException;

/**
 * Class NormalErrorController
 * @package ESD\Plugins\EasyRoute
 */
class NormalErrorController extends RouteController
{

    /**
     * Called when no method is found
     *
     * @param string|null $methodName
     * @return mixed
     * @throws RouteException
     */
    protected function defaultMethod(?string $methodName)
    {
        throw new RouteException("404 method $methodName can not find");
    }
}