<?php

/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Pool;

class Pools
{
    /**
     * @var array
     */
    protected array $poolList = [];

    /**
     * @return array
     */
    public function getPoolList(): array
    {
        return $this->poolList;
    }

    /**
     * @param string $name
     * @return Pool|null
     */
    public function getPool(string $name = "default"): ?Pool
    {
        return $this->poolList[$name] ?? null;
    }

    /**
     * @param Pool $pool
     */
    public function addPool(Pool $pool): void
    {
        $this->poolList[$pool->getConfig()->getName()] = $pool;
    }
}
