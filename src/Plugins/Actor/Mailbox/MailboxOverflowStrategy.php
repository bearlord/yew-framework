<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Mailbox;

use Yew\Core\Channel\Channel;

/**
 * Strategy applied when an Actor's mailbox is full.
 *
 */
interface MailboxOverflowStrategy
{
    /**
     * Attempt to enqueue $message into $channel.
     *
     * @param Channel $channel  The (bounded) mailbox channel
     * @param mixed   $message  The message to enqueue
     * @param float   $timeout  Max seconds to wait when the strategy allows blocking
     * @return bool True if the message was enqueued, false otherwise
     *
     * @throws \Throwable Implementations may throw to signal a hard rejection.
     */
    public function enqueue(Channel $channel, $message, float $timeout): bool;
}
