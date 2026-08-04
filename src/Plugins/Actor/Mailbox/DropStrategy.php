<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Mailbox;

use Yew\Core\Channel\Channel;

/**
 * Drop strategy: silently discard the message when the mailbox is full.
 *
 */
class DropStrategy implements MailboxOverflowStrategy
{
    /**
     * 丢弃策略：信箱已满时静默丢弃消息，否则立即入队。
     *
     * @param Channel $channel 有界信箱通道
     * @param mixed $message 待入队的消息
     * @param float $timeout 预留参数（本策略不阻塞）
     * @return bool 入队成功返回 true，信箱满返回 false
     */
    public function enqueue(Channel $channel, $message, float $timeout): bool
    {
        if ($channel->isFull()) {
            return false;
        }

        return $channel->push($message, 0);
    }
}
