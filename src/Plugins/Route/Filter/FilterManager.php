<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Filter;

use Yew\Plugins\Pack\ClientData;

class FilterManager
{
    /**
     * @var EveryFilterManager[]
     */
    protected $managers = [];

    /**
     * FilterManager constructor.
     */
    public function __construct()
    {
        $this->managers[AbstractFilter::FILTER_PRE] = new EveryFilterManager();
        $this->managers[AbstractFilter::FILTER_PRO] = new EveryFilterManager();
        $this->managers[AbstractFilter::FILTER_ROUTE] = new EveryFilterManager();
    }

    /**
     * @param AbstractFilter $filter
     */
    public function addFilter(AbstractFilter $filter)
    {
        $type = $filter->getType();
        
        if (is_string($type)) {
            $this->managers[$type]->addOrder($filter);
            $this->managers[$type]->order();
        } elseif(is_array($type)) {
            foreach ($type as $_type) {
                $this->managers[$_type]->addOrder($filter);
                $this->managers[$_type]->order();
            }
        }
    }

    /**
     * @param string $type
     * @param ClientData $clientData
     * @param null $data
     * @return int
     */
    public function filter(string $type, ClientData $clientData, $data = null): int
    {
        return $this->managers[$type]->filter($clientData, $data);
    }
}