<?php
/**
 * Yew framework
 * @author Lu Fei <lufei@simps.io>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\MQTT\Message;

use Yew\Plugins\MQTT\Hex\ReasonCode;
use Yew\Plugins\MQTT\Protocol\Types;
use Yew\Plugins\MQTT\Protocol\ProtocolV3;
use Yew\Plugins\MQTT\Protocol\ProtocolV5;

class PubRel extends AbstractMessage
{
    /**
     * @var int
     */
    protected int $messageId = 0;

    /**
     * @var int
     */
    protected int $code = ReasonCode::SUCCESS;

    /**
     * @return int
     */
    public function getMessageId(): int
    {
        return $this->messageId;
    }

    /**
     * @param int $messageId
     * @return $this
     */
    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

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
     * @param bool $isArray
     * @return array|mixed|string
     * @throws \Throwable
     */
    public function getContents(bool $isArray = false)
    {
        $buffer = [
            'type' => Types::PUBREL,
            'message_id' => $this->getMessageId(),
        ];

        if ($this->isMQTT5()) {
            $buffer['code'] = $this->getCode();
            $buffer['properties'] = $this->getProperties();
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
