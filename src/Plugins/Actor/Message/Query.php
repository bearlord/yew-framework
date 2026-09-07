<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Message;

/**
 * A Query: a request that expects a reply (Akka ask / request-response style).
 *
 * Queries are delivered through the non-oneway IPC path so the caller can
 * await the return value via {@see \Yew\Plugins\Actor\ActorFuture}.
 */
abstract class Query implements Message
{
    /**
     * @var mixed Typed request payload
     */
    protected $payload;

    /**
     * 构造查询请求，可携带任意类型载荷。
     *
     * @param mixed $payload 请求载荷
     */
    public function __construct($payload = null)
    {
        $this->payload = $payload;
    }

    /**
     * 获取查询携带的载荷。
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    abstract public function type(): MessageType;
}
