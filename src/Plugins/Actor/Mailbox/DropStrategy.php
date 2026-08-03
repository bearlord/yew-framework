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
    public function enqueue(Channel $channel, $message, float $timeout): bool
    {
        if ($channel->isFull()) {
            return false;
        }

        return $channel->push($message, 0);
    }
}
