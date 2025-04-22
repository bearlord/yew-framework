<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Channel;

use Yew\Core\DI\Factory;

class ChannelFactory implements Factory
{
    /**
     * @param array|null $params
     * @return ChannelImpl
     */
    public function create(?array $params = null): ChannelImpl
    {
        return new ChannelImpl($params[0] ?? 1);
    }
}