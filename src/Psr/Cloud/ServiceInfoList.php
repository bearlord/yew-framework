<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Psr\Cloud;

class ServiceInfoList
{
    /**
     * @var string
     */
    private $serviceName;

    /**
     * @var ServiceInfo[]
     */
    private $serviceInfos;

    /**
     * ServiceInfoList constructor.
     * @param string $serviceName
     * @param array $serviceInfos
     */
    public function __construct(string $serviceName, array $serviceInfos)
    {
        $this->serviceName = $serviceName;
        $this->serviceInfos = $serviceInfos;
    }

    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @param string $serviceName
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return ServiceInfo[]
     */
    public function getServiceInfos(): array
    {
        return $this->serviceInfos;
    }

    /**
     * @param ServiceInfo[] $serviceInfos
     */
    public function setServiceInfos(array $serviceInfos): void
    {
        $this->serviceInfos = $serviceInfos;
    }
}