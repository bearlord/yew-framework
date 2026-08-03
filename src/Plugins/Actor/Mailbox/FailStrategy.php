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
    public function enqueue(Channel $channel, $message, float $timeout): bool
    {
        if ($channel->isFull()) {
            throw new ActorException('Actor mailbox is full; message rejected by FailStrategy');
        }

        return $channel->push($message, 0);
    }
}
