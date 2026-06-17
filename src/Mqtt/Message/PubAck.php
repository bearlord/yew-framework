<?php
/**
 * Yew framework
 * @author Lu Fei <lufei@simps.io>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Mqtt\Message;

use Yew\Mqtt\Hex\ReasonCode;
use Yew\Mqtt\Protocol\Types;
use Yew\Mqtt\Protocol\ProtocolV3;
use Yew\Mqtt\Protocol\ProtocolV5;

class PubAck extends AbstractMessage
{
    protected int $messageId = 0;

    protected int $code = ReasonCode::SUCCESS;

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getContents(bool $isArray = false)
    {
        $buffer = [
            "type" => Types::PUBACK,
            "message_id" => $this->getMessageId(),
        ];

        if ($this->isMQTT5()) {
            $buffer["code"] = $this->getCode();
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
