<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp\Message;

use Yew\Plugins\Amqp\Builder\QueueBuilder;
use PhpAmqpLib\Message\AMQPMessage;

interface ConsumerMessageInterface extends MessageInterface
{
    public function consumeMessage($data, AMQPMessage $message): string;

    public function setQueue(string $queue);

    public function getQueue(): string;

    public function isRequeue(): bool;

    public function getQos(): ?array;

    public function getQueueBuilder(): QueueBuilder;

    public function getConsumerTag(): string;

    public function isEnable(): bool;

    public function setEnable(bool $enable);

    public function getMaxConsumption(): int;

    public function setMaxConsumption(int $maxConsumption);

    /**
     * @return float|int
     */
    public function getWaitTimeout();

    /**
     * @param float|int $timeout
     */
    public function setWaitTimeout($timeout);
}
