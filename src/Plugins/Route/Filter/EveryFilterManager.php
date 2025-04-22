<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Filter;

use Yew\Core\Order\OrderOwnerTrait;
use Yew\Plugins\Pack\ClientData;

/**
 * Class EveryFilterManager
 * @package Yew\Plugins\Route\Filter
 */
class EveryFilterManager
{
    use OrderOwnerTrait;

    /**
     * @param ClientData $clientData
     * @return int
     */
    public function filter(ClientData $clientData): int
    {
        /** @var AbstractFilter $order */
        foreach ($this->orderList as $order) {
            if ($order->isEnable($clientData)) {
                $code = $order->filter($clientData);
                if ($code < AbstractFilter::RETURN_NEXT) {
                    return $code;
                }
            }
        }
        return AbstractFilter::RETURN_END_FILTER;
    }
}