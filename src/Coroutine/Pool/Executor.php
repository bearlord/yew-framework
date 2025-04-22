<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Pool;

/**
 * Interface Executor
 * @package Yew\Coroutine\Pool
 */
interface Executor
{
    /**
     * Execute task
     *
     * @param $runnable
     * @return mixed
     */
    public function execute($runnable);
}