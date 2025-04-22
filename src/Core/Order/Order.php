<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Order;

use Yew\Core\Exception\Exception;

abstract class Order implements OrderInterface
{
    /**
     * @var string[]
     */
    private $afterClass = [];

    /**
     * @var string[]
     */
    private $beforeClass = [];

    /**
     * @var int
     */
    private $orderIndex = 1;

    /**
     * @var Order[]
     */
    private $afterOrder = [];

    /**
     * @inheritDoc
     * @param $className
     */
    public function atAfter(...$className)
    {
        foreach ($className as $one) {
            $this->afterClass[$one] = $one;
        }
    }

    /**
     * @inheritDoc
     * @param $className
     */
    public function atBefore(...$className)
    {
        foreach ($className as $one) {
            $this->beforeClass[$one] = $one;
        }
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function getAfterClass(): array
    {
        return $this->afterClass;
    }

    /**
     * @inheritDoc
     * @param Order $root
     * @param int $layer
     * @return int
     * @throws Exception
     */
    public function getOrderIndex(Order $root, int $layer): int
    {
        $layer++;
        if ($layer > 255) {
            throw new Exception(get_class($root) . "Circular dependency in the plugin ordering, please check the plugin");
        }
        $max = $this->orderIndex;
        foreach ($this->afterOrder as $order) {
            $value = $this->orderIndex + $order->getOrderIndex($root, $layer);
            $max = max($max, $value);
        }
        return $max;
    }

    /**
     * @inheritDoc
     * @param Order $afterOrder
     */
    public function addAfterOrder(Order $afterOrder): void
    {
        $this->afterOrder[] = $afterOrder;
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function getBeforeClass(): array
    {
        return $this->beforeClass;
    }
}