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

    public function __construct($payload = null)
    {
        $this->payload = $payload;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    abstract public function type(): MessageType;
}
