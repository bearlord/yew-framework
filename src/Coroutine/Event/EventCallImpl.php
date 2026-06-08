<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Event;

use Yew\Core\Plugins\Event\EventCall;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Coroutine\Channel\ChannelImpl;

class EventCallImpl extends ChannelImpl implements EventCall
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $once;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * EventCallImpl constructor.
     * @param EventDispatcher $eventDispatcher
     * @param string $type
     * @param bool $once
     */
    public function __construct(EventDispatcher $eventDispatcher, string $type, bool $once = false)
    {
        parent::__construct();
        $this->type = $type;
        $this->once = $once;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return bool
     */
    public function isOnce(): bool
    {
        return $this->once;
    }

    /**
     * @param bool $once
     */
    public function setOnce(bool $once): void
    {
        $this->once = $once;
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * @param callable $callback
     * @return mixed|void
     */
    public function call(callable $callback)
    {
        goWithContext(function () use ($callback) {
            while (true) {
                $result = $this->pop();
                $callback($result);
                if ($this->once) {
                    $this->eventDispatcher->remove($this->type, $this);
                    break;
                }
            }
        });
    }

    /**
     * @param int|null $timeout
     * @return mixed
     */
    public function wait(?int $timeout = 5)
    {
        return $this->pop($timeout);
    }

    /**
     * @return mixed|void
     */
    public function destroy()
    {
        $this->close();
    }

    /**
     * @param $data
     * @return void
     */
    public function send($data)
    {
        $this->push($data);
    }
}
