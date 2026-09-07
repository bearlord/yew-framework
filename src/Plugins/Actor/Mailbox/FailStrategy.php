<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Mailbox;

use Yew\Core\Channel\Channel;
use Yew\Plugins\Actor\Exception\ActorException;

/**
 * Fail strategy: reject the message by throwing when the mailbox is full.
 *
 */
class FailStrategy implements MailboxOverflowStrategy
{
    /**
     * 失败策略：信箱已满时抛出 ActorException 拒绝消息，否则立即入队。
     *
     * @param Channel $channel 有界信箱通道
     * @param mixed $message 待入队的消息
     * @param float $timeout 预留参数（本策略不阻塞）
     * @return bool 入队成功返回 true
     * @throws ActorException 信箱已满时抛出
     */
    public function enqueue(Channel $channel, $message, float $timeout): bool
    {
        if ($channel->isFull()) {
            throw new ActorException('Actor mailbox is full; message rejected by FailStrategy');
        }

        return $channel->push($message, 0);
    }
}
