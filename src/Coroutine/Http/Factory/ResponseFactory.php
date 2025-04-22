<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Http\Factory;

use Yew\Core\DI\Factory;
use Yew\Coroutine\Http\SwooleResponse;

/**
 * Class ResponseFactory
 * @package Yew\Server\Coroutine\Http\Factory
 */
class ResponseFactory implements Factory
{

    /**
     * @param array|null $params
     * @return SwooleResponse
     */
    public function create(?array $params = null): SwooleResponse
    {
        return new SwooleResponse();
    }
}