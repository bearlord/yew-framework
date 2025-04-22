<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Http\Factory;

use Yew\Core\DI\Factory;
use Yew\Coroutine\Http\SwooleRequest;

/**
 * Class RequestFactory
 * @package Yew\Server\Coroutine\Http\Factory
 */
class RequestFactory implements Factory
{

    /**
     * @param array|null $params
     * @return SwooleRequest
     */
    public function create(?array $params = null): SwooleRequest
    {
        return new SwooleRequest();
    }
}