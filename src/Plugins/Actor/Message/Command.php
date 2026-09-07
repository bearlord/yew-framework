<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Message;

/**
 * A Command: an intent to mutate actor state (fire-and-forget / tell style).
 *
 * Subclass with a payload and declare its type via a PHP 8 enum:
 *
 *   final class CreateOrder extends Command {
 *       public function __construct(public array $payload) {}
 *       public function type(): MessageType { return OrderCommand::Create; }
 *   }
 */
abstract class Command implements Message
{
    /**
     * @var mixed Typed payload carried by the command
     */
    protected $payload;

    /**
     * 构造命令，可携带任意类型载荷。
     *
     * @param mixed $payload 命令载荷
     */
    public function __construct($payload = null)
    {
        $this->payload = $payload;
    }

    /**
     * 获取命令携带的载荷。
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    abstract public function type(): MessageType;
}
