<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Psr\Cloud;

interface Services
{
    /** Get Service info list
     *
     * @param string $service
     * @return ServiceInfoList|null
     */
    public function getServices(string $service): ?ServiceInfoList;

    /**
     * Get Service info
     *
     * @param string $service
     * @return ServiceInfo|null
     */
    public function getService(string $service): ?ServiceInfo;
}