<?php
/**
 * Yew framework
 * @author Lu Fei <lufei@simps.io>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\MQTT\Message;

use Yew\Plugins\MQTT\Protocol\Types;
use Yew\Plugins\MQTT\Protocol\ProtocolV3;
use Yew\Plugins\MQTT\Protocol\ProtocolV5;

class SubAck extends AbstractMessage
{
    /**
     * @var int
     */
    protected int $messageId = 0;

    /**
     * @var array 
     */
    protected array $codes = [];

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
     * @return array
     */
    public function getCodes(): array
    {
        return $this->codes;
    }

    /**
     * @param array $codes
     * @return $this
     */
    public function setCodes(array $codes): self
    {
        $this->codes = $codes;

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
            'type' => Types::SUBACK,
            'message_id' => $this->getMessageId(),
            'codes' => $this->getCodes(),
        ];

        if ($this->isMQTT5()) {
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
