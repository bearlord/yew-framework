<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\MQTT\Auth;

class EasyMqttAuth implements MqttAuth
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
