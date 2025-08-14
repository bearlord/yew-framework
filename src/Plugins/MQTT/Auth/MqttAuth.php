<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\MQTT\Auth;

interface MqttAuth
{
    public function auth(int $fd, string $username, string $password): array;
}
