<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace ESD\Psr\Cloud;

/**
 * Interface CircuitBreaker
 * @package ESD\Psr\Cloud
 */
interface CircuitBreaker
{
    /**
     * @param $service
     * @return mixed
     */
    public function failure($service);

    /**
     * @param $service
     * @return mixed
     */
    public function success($service);

    /**
     * @param $service
     * @return mixed
     */
    public function isAvailable($service);

    /**
     * @return mixed
     */
    public function reset();

    /**
     * @param $enable
     * @return mixed
     */
    public function setEnable($enable);

    /**
     * @return mixed
     */
    public function isEnable();
}