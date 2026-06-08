<?php
/**
 * ESD Yii Queue plugin
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Queue;

/**
 * Class QueuePools
 * @package Yew\Plugins\Queue
 */
class QueuePools
{
    /**
     * @var array
     */
    protected $poolList = [];

    /**
     * Get pool
     *
     * @param $name
     * @return
     */
    public function getPool(string $name = "default")
    {
        return $this->poolList[$name] ?? null;
    }

    /**
     * @param string $name
     * @param QueuePool $pool
     */
    public function addPool(string $name, QueuePool $pool)
    {
        $this->poolList[$name] = $pool;
    }
}
