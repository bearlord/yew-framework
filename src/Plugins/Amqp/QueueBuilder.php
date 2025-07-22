<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp;

class QueueBuilder extends Builder
{
    /**
     * @var string
     */
    protected string $queue;

    /**
     * @var bool
     */
    protected bool $exclusive = false;

    /**
     * @var array
     */
    protected array $arguments = [
        "x-ha-policy" => ["S", "all"],
    ];

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * @param string $queue
     * @return $this
     */
    public function setQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * @return bool
     */
    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    /**
     * @param bool $exclusive
     * @return $this
     */
    public function setExclusive(bool $exclusive): self
    {
        $this->exclusive = $exclusive;
        return $this;
    }
}
