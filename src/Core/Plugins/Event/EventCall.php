<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

/**
 * Interface EventCall
 * @package Yew\Core\Plugins\Event
 */
interface EventCall
{
    /**
     * EventCall constructor.
     * @param EventDispatcher $eventDispatcher
     * @param string $type
     * @param bool $once
     */
    public function __construct(EventDispatcher $eventDispatcher, string $type, bool $once = false);

    /**
     * @param $data
     * @return mixed
     */
    public function send($data);

    /**
     * @param callable $callback
     * @return mixed
     */
    public function call(callable $callback);

    /**
     * @param int|null $timeout
     * @return mixed
     */
    public function wait(?int $timeout = 5);
    /**
     * @return mixed
     */
    public function destroy();

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $type
     */
    public function setType(string $type): void;

    /**
     * @return bool
     */
    public function isOnce(): bool;

    /**
     * @param bool $once
     */
    public function setOnce(bool $once): void;

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher;
}
