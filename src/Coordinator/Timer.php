<?php

namespace Yew\Coordinator;

use Yew\Coroutine\Server\Server;

class Timer
{

    public const STOP = 'stop';

    /**
     * @var array
     */
    private array $closures = [];

    /**
     * @var int
     */
    private int $id = 0;

    /**
     * @var int
     */
    public static int $count = 0;

    /**
     * @var int
     */
    public static int $round = 0;


    /**
     * @param float $timeout
     * @param callable $closure
     * @param string $identifier
     * @return int
     */
    public function after(float $timeout, callable $closure, string $identifier = Constants::WORKER_EXIT): int
    {
        $id = ++$this->id;
        $this->closures[$id] = true;
        \Swoole\Coroutine::create(function () use ($timeout, $closure, $identifier, $id) {
            try {
                ++Timer::$count;

                if ($timeout > 0) {
                    $isClosing = CoordinatorManager::until($identifier)->yield($timeout); // Run after $timeout seconds.
                } elseif ($timeout == 0) {
                    $isClosing = CoordinatorManager::until($identifier)->isClosing(); // Run immediately.
                } else {
                    $isClosing = CoordinatorManager::until($identifier)->yield(); // Run until $identifier resume.
                }

                if (isset($this->closures[$id])) {
                    $closure($isClosing);
                }
            } finally {
                unset($this->closures[$id]);
                --Timer::$count;
            }
        });
        return $id;
    }

    /**
     * @param float $timeout
     * @param callable $closure
     * @param string $identifier
     * @return int
     */
    public function tick(float $timeout, callable $closure, string $identifier = Constants::WORKER_EXIT): int
    {
        $id = ++$this->id;
        $this->closures[$id] = true;
        \Swoole\Coroutine::create(function () use ($timeout, $closure, $identifier, $id) {
            try {
                $round = 0;
                ++Timer::$count;
                while (true) {
                    $isClosing = CoordinatorManager::until($identifier)->yield(max($timeout, 0.000001));
                    if (!isset($this->closures[$id])) {
                        break;
                    }

                    $result = null;

                    try {
                        $result = $closure($isClosing);
                    } catch (\Exception $exception) {
                        Server::$instance->getLog()->error((string)$exception);
                    }

                    if ($result === self::STOP || $isClosing) {
                        break;
                    }

                    ++$round;
                    ++Timer::$round;
                }
            } finally {
                unset($this->closures[$id]);
                Timer::$round -= $round;
                --Timer::$count;
            }
        });
        return $id;
    }

    /**
     * @param callable $closure
     * @param string $identifier
     * @return int
     */
    public function until(callable $closure, string $identifier = Constants::WORKER_EXIT): int
    {
        return $this->after(-1, $closure, $identifier);
    }

    /**
     * @param int $id
     * @return void
     */
    public function clear(int $id): void
    {
        unset($this->closures[$id]);
    }

    /**
     * @return void
     */
    public function clearAll(): void
    {
        $this->closures = [];
    }

    /**
     * @return array
     */
    public static function stats(): array
    {
        return [
            'num' => Timer::$count,
            'round' => Timer::$round,
        ];
    }
}
