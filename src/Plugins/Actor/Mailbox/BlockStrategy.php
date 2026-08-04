<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Mailbox;

use Yew\Core\Channel\Channel;

/**
 * Blocking strategy: wait up to $timeout seconds for free mailbox space.
 *
 */
class BlockStrategy implements MailboxOverflowStrategy
{
    /**
     * 阻塞策略：在超时时间内等待信箱出现空闲空间后入队。
     *
     * @param Channel $channel 有界信箱通道
     * @param mixed $message 待入队的消息
     * @param float $timeout 最大阻塞等待秒数
     * @return bool 入队成功返回 true
     */
    public function enqueue(Channel $channel, $message, float $timeout): bool
    {
        return $channel->push($message, $timeout);
    }
}
