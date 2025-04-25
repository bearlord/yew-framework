<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Controller;

interface IController
{
    /**
     * @param string|null $controllerName
     * @param string|null $methodName
     * @param array|null $params
     * @return mixed
     */
    public function handle(?string $controllerName, ?string $methodName, ?array $params);

    /**
     * @param string|null $controllerName
     * @param string|null $methodName
     * @return mixed
     */
    public function initialization(?string $controllerName, ?string $methodName);

    /**
     * @param \Throwable $exception
     * @return mixed
     */
    public function onExceptionHandle(\Throwable $exception);
}