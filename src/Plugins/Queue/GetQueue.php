<?php
/**
 * ESD Yii Queue plugin
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Queue;

use Yew\Framework\Queue\Cli\Queue;
use Yew\Yew;

/**
 * Trait GetQueue
 * @package Yew\Plugins\Queue
 */
trait GetQueue
{

    /**
     * @param string $name
     * @return Queue
     */
    public function queue($name = "default")
    {
        $poolKey = $name;
        $contextKey = sprintf("Queue:%s", $name);

        $queue = getDeepContextValue($contextKey);

        if (empty($queue)) {
            /** @var QueuePools $pools */
            $pools = getDeepContextValueByClassName(QueuePools::class);

            /** @var QueuePool $pool */
            $pool = $pools->getPool($poolKey);

            if ($pool == null) {
                throw new \Exception("No Queue pool named {$poolKey} was found");
            }

            return $pool->handle();
        }

        return $queue;
    }
}