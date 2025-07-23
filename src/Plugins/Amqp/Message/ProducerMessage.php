<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp\Message;

use Yew\Framework\Helpers\Json;

/**
 * Class ProducerMessage
 * @package Yew\Plugins\Amqp\Message
 */
class ProducerMessage extends Message
{
    /**
     * @var string
     */
    protected $queue;

    /**
     * @var mixed
     */
    protected $payload;

    /**
     * @var array
     */
    protected $properties = [
        "content_type" => "text/plain",
        "delivery_mode" => self::DELIVERY_MODE_PERSISTENT
    ];

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * @param string $queue
     */
    public function setQueue(string $queue): void
    {
        $this->queue = $queue;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setPayload($data): self
    {
        $this->payload = $data;
        return $this;
    }

    /**
     * @return string
     */
    public function payload()
    {
        return $this->payload;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $data
     * @return mixed
     */
    public function unserialize(string $data)
    {
        return json_decode($data, true);
    }
}