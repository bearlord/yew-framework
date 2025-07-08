<?php
/**
 * Yew framework
 * @author tmtbe <565364226@qq.com>
 */

namespace Yew\Coroutine;

use Yew\Core\Server\Server;
use Yew\Core\Channel\Channel;

/**
 * @method bool isFull()
 * @method bool isEmpty()
 */
class Concurrent
{
    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @var int
     */
    protected $limit;

    public function __construct(int $limit)
    {
        $this->limit = $limit;
        $this->channel = DIGet(Channel::class, [$limit]);
    }

    /**
     * @param $name
     * @param $arguments
     * @return void
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, ['isFull', 'isEmpty'])) {
            return $this->channel->{$name}(...$arguments);
        }
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function length(): int
    {
        return $this->channel->length();
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->channel->length();
    }

    /**
     * @return int
     */
    public function getRunningCoroutineCount(): int
    {
        return $this->length();
    }

    /**
     * @param callable $callable
     * @return void
     * @throws \Exception
     */
    public function create(callable $callable): void
    {
        $this->channel->push(true);
        
        $context = getContext();
        \Swoole\Coroutine::create(function () use ($callable, $context) {
            $currentContext = getContext();
            //Reset parent context
            $currentContext->setParentContext($context);
            try {
                $callable();
            } catch (\Throwable $exception) {
                Server::$instance->getLog()->error($exception);
            } finally {
                $this->channel->pop();
            }
        });
    }
}
