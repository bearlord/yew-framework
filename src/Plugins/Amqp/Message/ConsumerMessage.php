<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp\Message;

use Yew\Plugins\Amqp\Builder\QueueBuilder;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumerMessage extends Message
{
    /**
     * @var string
     */
    protected string $queue;

    /**
     * @var bool
     */
    protected $requeue = true;

    /**
     * @var array
     */
    protected $routingKey = [];

    /**
     * @var array
     */
    protected $qos = [
        "prefetch_size" => 0,
        "prefetch_count" => 1,
        "global" => false,
    ];

    /**
     * @var bool
     */
    protected $enable = true;

    /**
     * @var int
     */
    protected $maxConsumption = 0;

    /**
     * @var float|int
     */
    protected $waitTimeout = 0;

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
     * @return bool
     */
    public function isRequeue(): bool
    {
        return $this->requeue;
    }

    /**
     * @param bool $requeue
     */
    public function setRequeue(bool $requeue): void
    {
        $this->requeue = $requeue;
    }

    /**
     * @return array|null
     */
    public function getQos(): ?array
    {
        return $this->qos;
    }

    /**
     * @param array|null $qos
     */
    public function setQos(?array $qos): void
    {
        $this->qos = $qos;
    }

    public function getQueueBuilder(): QueueBuilder
    {
        return (new QueueBuilder())->setQueue($this->getQueue());
    }

    /**
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enable;
    }

    /**
     * @param bool $enable
     */
    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    /**
     * @return int
     */
    public function getMaxConsumption(): int
    {
        return $this->maxConsumption;
    }

    /**
     * @param int $maxConsumption
     */
    public function setMaxConsumption(int $maxConsumption): void
    {
        $this->maxConsumption = $maxConsumption;
    }

    /**
     * @return float|int
     */
    public function getWaitTimeout()
    {
        return $this->waitTimeout;
    }

    /**
     * @param float|int $waitTimeout
     */
    public function setWaitTimeout($waitTimeout): void
    {
        $this->waitTimeout = $waitTimeout;
    }

    public function getConsumerTag(): string
    {
        return implode(",", (array) $this->getRoutingKey());
    }

    /**
     * @param $data
     * @param AMQPMessage $message
     * @return void
     */
    protected function reply($data, AMQPMessage $message)
    {
        /** @var AMQPChannel $channel */
        $channel = $message->delivery_info["channel"];
        $channel->basic_publish(
            new AMQPMessage($data, [
                "correlation_id" => $message->get("correlation_id"),
            ]),
            "",
            $message->get("reply_to")
        );
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

    /**
     * @param $data
     * @param AMQPMessage $message
     * @return string
     */
    public function consumeMessage($data, AMQPMessage $message): string
    {
        return $this->consume($data);
    }

    /**
     * @param $data
     * @return string
     */
    public function consume($data): string
    {
        return Result::ACK;
    }
}