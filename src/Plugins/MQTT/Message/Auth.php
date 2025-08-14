<?php
/**
 * Yew framework
 * @author Lu Fei <lufei@simps.io>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\MQTT\Message;

use Yew\Plugins\MQTT\Hex\ReasonCode;
use Yew\Plugins\MQTT\Protocol\Types;
use Yew\Plugins\MQTT\Protocol\ProtocolV5;

class Auth extends AbstractMessage
{
    /**
     * @var int
     */
    protected int $code = ReasonCode::SUCCESS;

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * AUTH type is only available in MQTT5
     *
     * @param bool $isArray
     * @return array|mixed|string
     */
    public function getContents(bool $isArray = false)
    {
        $buffer = [
            'type' => Types::AUTH,
            'code' => $this->getCode(),
            'properties' => $this->getProperties(),
        ];

        if ($isArray) {
            return $buffer;
        }

        return ProtocolV5::pack($buffer);
    }
}
