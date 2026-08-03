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
    public function enqueue(Channel $channel, $message, float $timeout): bool
    {
        return $channel->push($message, $timeout);
    }
}
