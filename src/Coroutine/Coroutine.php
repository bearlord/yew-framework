<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine;

use Yew\Core\Context\Context;
use Yew\Core\Context\ContextManager;
use Yew\Core\Runtime;
use Yew\Coroutine\Pool\Runnable;

/**
 * Class Coroutine
 * @package Yew\Coroutine
 */
class Coroutine
{

    /**
     * Enable coroutine
     */
    public static function enableCoroutine(): void
    {
        Runtime::$enableCoroutine = true;
        ContextManager::getInstance()->registerContext(new CoroutineContextBuilder());
    }

    /**
     * Coroutine set
     * @param $data
     */
    public static function set($data): void
    {
        \Swoole\Coroutine::set($data);
    }

    /**
     * Get coroutine stats
     * @return array
     */
    public static function getStats(): array
    {
        return \Swoole\Coroutine::stats();
    }

    /**
     * Determines whether the specified coroutine exists
     * @return bool
     */
    public static function isExist($coId): bool
    {
        return \Swoole\Coroutine::exists($coId);
    }

    /**
     * Get the unique ID of the current coroutine.
     * Its alias is getUid, which is a unique positive integer within a process.
     *
     * @return int
     */
    public static function getCid(): int
    {
        return \Swoole\Coroutine::getCid();
    }

    /**
     * 获取当前协程的父协程ID
     * @return int
     */
    public static function getPcid(): int
    {
        return \Swoole\Coroutine::getPcid();
    }

    /**
     * Get the context object of the current coroutine
     *
     * @return mixed
     */

    public static function getSwooleContext()
    {
        return \Swoole\Coroutine::getContext();
    }

    /**
     * Get the context object of the current coroutine
     * @return Context
     */
    public static function getContext(): Context
    {
        $result = self::getSwooleContext()[Context::storageKey] ?? null;
        if ($result == null) {
            self::getSwooleContext()[Context::storageKey] = new Context(null);
        }
        return self::getSwooleContext()[Context::storageKey];
    }

    /**
     * Get the parent context of the current coroutine
     *
     * @return Context|null
     */
    public static function getParentContext(): ?Context
    {
        return self::getContext()->getParentContext();
    }

    /**
     * Iterate through all coroutines in the current process.
     * @return \Iterator
     */
    public static function getListCoroutines(): \Iterator
    {
        return \Swoole\Coroutine::listCoroutines();
    }

    /**
     * Get the coroutine function call stack.
     *
     * @param int $cid
     * @param int $options
     * @param int $limit
     * @return array
     */
    public static function getBackTrace(int $cid = 0, int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT, int $limit = 0): array
    {
        return \Swoole\Coroutine::getBackTrace($cid, $options, $limit);
    }

    /**
     * Give up the right to execute the current coroutine.
     */
    public static function yield()
    {
        \Swoole\Coroutine::yield();
    }

    /**
     * Sleep
     *
     * @param float $se
     */
    public static function sleep(float $se)
    {
        \Swoole\Coroutine::sleep($se);
    }


    /**
     * Give up the right to execute the current coroutine.
     *
     * @param int $coroutineId
     */
    public static function resume(int $coroutineId)
    {
        \Swoole\Coroutine::resume($coroutineId);
    }

    /**
     * Run task
     *
     * @param $runnable
     * @return int|bool
     */
    public static function runTask($runnable)
    {
        return goWithContext(function () use ($runnable) {
            if ($runnable != null) {
                if ($runnable instanceof Runnable) {
                    $result = $runnable->run();
                    $runnable->sendResult($result);
                }
                if (is_callable($runnable)) {
                    $runnable();
                }
            }
        });
    }

}
