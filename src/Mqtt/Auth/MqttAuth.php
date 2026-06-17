<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Mqtt\Auth;

class MqttAuth implements MqttAuthInterfa
{

    /**
     * @param int $fd
     * @param string $username
     * @param string $password
     * @return array
     */
    public function auth(int $fd, string $username, string $password): array
    {
        return ["true", $fd];
    }
}
