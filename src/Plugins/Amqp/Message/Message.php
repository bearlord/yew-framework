<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp\Message;

use Yew\Plugins\Amqp\Builder\ExchangeBuilder;

abstract class Message implements MessageInterface
{
    const DELIVERY_MODE_NON_PERSISTENT = 1;

    const DELIVERY_MODE_PERSISTENT = 2;

    /**
     * @var string
     */
    protected $poolName = "default";

    /**
     * @var string
     */
    protected $exchange = "";

    /**
     * @var string
     */
    protected $type = Type::TOPIC;

    /**
     * @var array|string
     */
    protected $routingKey = "";

    /**
     * @return string
     */
    public function getExchange(): string
    {
        return $this->exchange;
    }

    /**
     * @param string $exchange
     */
    public function setExchange(string $exchange): void
    {
        $this->exchange = $exchange;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return array|string
     */
    public function getRoutingKey()
    {
        return $this->routingKey;
    }

    /**
     * @param array|string $routingKey
     */
    public function setRoutingKey($routingKey): void
    {
        $this->routingKey = $routingKey;
    }

    /**
     * @return string
     */
    public function getPoolName(): string
    {
        return $this->poolName;
    }

    /**
     * @return ExchangeBuilder
     */
    public function getExchangeBuilder(): ExchangeBuilder
    {
        return (new ExchangeBuilder())->setExchange($this->getExchange())->setType($this->getType());
    }

    abstract public function serialize(): string;

    abstract public function unserialize(string $data);
}