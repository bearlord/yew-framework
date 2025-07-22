<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp\Message;

use Yew\Plugins\Amqp\Builder\ExchangeBuilder;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * @method string getExchange()
 * @method string getType()
 * @property array $properties
 */
trait ProducerDelayedMessageTrait
{
    /**
     * Set the delay time.
     * @return $this
     */
    public function setDelayMs(int $millisecond, string $name = "x-delay"): self
    {
        $this->properties["application_headers"] = new AMQPTable([$name => $millisecond]);
        return $this;
    }

    /**
     * Overwrite.
     */
    public function getExchangeBuilder(): ExchangeBuilder
    {
        return (new ExchangeBuilder())->setExchange($this->getExchange())
            ->setType("x-delayed-message")
            ->setArguments(new AMQPTable(["x-delayed-type" => $this->getType()]));
    }
}
