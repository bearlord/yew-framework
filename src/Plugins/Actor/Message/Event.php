<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Message;

/**
 * An Event: a fact that already happened, used for notification / event
 * sourcing. Events are typically emitted after a command mutates state.
 *
 * Note: persistence already defines {@see \Yew\Plugins\Actor\Persistence\ActorEvent}.
 * This lightweight marker is for in-process / multicast event semantics and
 * can be adapted to the persistence event type when sourcing is enabled.
 */
abstract class Event implements Message
{
    /**
     * @var mixed Event payload
     */
    protected $payload;

    /**
     * 构造事件，可携带任意类型载荷。
     *
     * @param mixed $payload 事件载荷
     */
    public function __construct($payload = null)
    {
        $this->payload = $payload;
    }

    /**
     * 获取事件携带的载荷。
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    abstract public function type(): MessageType;
}
