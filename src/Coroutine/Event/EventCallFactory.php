<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Event;

use Yew\Core\DI\Factory;

class EventCallFactory implements Factory
{
    /**
     * @param array|null $params
     * @return EventCallImpl
     */
    public function create(?array $params = null)
    {
        return new EventCallImpl($params[0], $params[1], $params[2] ?? false);
    }
}