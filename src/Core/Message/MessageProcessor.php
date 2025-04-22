<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Message;

use Yew\Core\Exception\Exception;

/**
 * Class MessageProcessor
 * @package Yew\Core\Message
 */
abstract class MessageProcessor
{
    /**
     * @var MessageProcessor[]
     */
    private static array $messageProcessorMap = [];

    /**
     * Message type
     * @var string
     */
    protected string $type;


    /**
     * MessageProcessor constructor.
     * @param string $type
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Add message processor
     *
     * @param MessageProcessor $messageProcessor
     * @param bool $overwrite
     * @throws Exception
     */
    public static function addMessageProcessor(MessageProcessor $messageProcessor, bool $overwrite = false)
    {
        if (isset(self::$messageProcessorMap[$messageProcessor->type]) && !$overwrite) {
            throw new Exception("A message handler of the same type already exists");
        }
        self::$messageProcessorMap[$messageProcessor->type] = $messageProcessor;
    }

    /**
     * Dispatch message
     *
     * @param Message $message
     * @return bool
     */
    public static function dispatch(Message $message): bool
    {
        $processor = self::$messageProcessorMap[$message->getType()] ?? null;
        if ($processor != null) {
            return $processor->handler($message);
        }
        return false;
    }

    /**
     * Handler for process message
     *
     * @param Message $message
     * @return mixed
     */
    abstract public function handler(Message $message): bool;
}