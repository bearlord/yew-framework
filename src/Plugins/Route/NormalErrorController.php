<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route;

use Yew\Plugins\Route\Controller\RouteController;

/**
 * Class NormalErrorController
 * @package Yew\Plugins\Route
 */
class NormalErrorController extends RouteController
{

    /**
     * Called when no method is found
     *
     * @param $methodName
     * @return mixed
     * @throws RouteException
     */
    protected function defaultMethod(?string $methodName)
    {
        throw new RouteException("404 method $methodName can not find");
    }
}