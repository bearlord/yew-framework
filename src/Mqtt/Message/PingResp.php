<?php
/**
 * Yew framework
 * @author Lu Fei <lufei@simps.io>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Mqtt\Message;

use Yew\Mqtt\Protocol\Types;
use Yew\Mqtt\Protocol\ProtocolV3;
use Yew\Mqtt\Protocol\ProtocolV5;

class PingResp extends AbstractMessage
{
    /**
     * @param bool $isArray
     * @return array|string
     * @throws \Throwable
     */
    public function getContents(bool $isArray = false)
    {
        $buffer = [
            "type" => Types::PINGRESP,
        ];

        if ($isArray) {
            return $buffer;
        }

        if ($this->isMQTT5()) {
            return ProtocolV5::pack($buffer);
        }

        return ProtocolV3::pack($buffer);
    }
}
