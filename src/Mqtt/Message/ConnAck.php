<?php
/**
 * Yew framework
 * @author Lu Fei <lufei@simps.io>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Mqtt\Message;

use Yew\Mqtt\Hex\ReasonCode;
use Yew\Mqtt\Protocol\ProtocolInterface;
use Yew\Mqtt\Protocol\Types;
use Yew\Mqtt\Protocol\ProtocolV3;
use Yew\Mqtt\Protocol\ProtocolV5;

class ConnAck extends AbstractMessage
{
    /**
     * @var int
     */
    protected int $code = ReasonCode::SUCCESS;

    /**
     * @var int
     */
    protected int $sessionPresent = ProtocolInterface::MQTT_SESSION_PRESENT_0;

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
     * @return int
     */
    public function getSessionPresent(): int
    {
        return $this->sessionPresent;
    }

    /**
     * @param int $sessionPresent
     * @return $this
     */
    public function setSessionPresent(int $sessionPresent): self
    {
        $this->sessionPresent = $sessionPresent;

        return $this;
    }

    /**
     * @param bool $isArray
     * @return array|string
     * @throws \Throwable
     */
    public function getContents(bool $isArray = false)
    {
        $buffer = [
            "type" => Types::CONNACK,
            "code" => $this->getCode(),
            "session_present" => $this->getSessionPresent(),
        ];

        if ($this->isMQTT5()) {
            $buffer["properties"] = $this->getProperties();
        }

        if ($isArray) {
            return $buffer;
        }

        if ($this->isMQTT5()) {
            return ProtocolV5::pack($buffer);
        }

        return ProtocolV3::pack($buffer);
    }
}
