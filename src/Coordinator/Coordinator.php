<?php

namespace Yew\Coordinator;

use Yew\Core\Channel\Channel;

class Coordinator
{
    private Channel $channel;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->channel = DIGet(Channel::class, [1]);
    }

    /**
     * Yield the current coroutine for a given timeout,
     * unless the coordinator is wakeup from outside.
     *
     * @param float|int $timeout
     * @return bool returns true if the coordinator has been woken up
     */
    public function yield($timeout = -1): bool
    {
        $this->channel->pop((float) $timeout);
        return $this->channel->isClosing();
    }

    /**
     * @return bool
     */
    public function isClosing(): bool
    {
        return $this->channel->isClosing();
    }

    /**
     * Wakeup all coroutines yielding for this coordinator.
     */
    public function resume(): void
    {
        $this->channel->close();
    }
}
