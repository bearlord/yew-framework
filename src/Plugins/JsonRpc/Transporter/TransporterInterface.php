<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc\Transporter;

use Yew\LoadBalance\LoadBalancerInterface;

interface TransporterInterface
{
    public function send(string $data);

    public function recv();

    public function getLoadBalancer(): ?LoadBalancerInterface;

    public function setLoadBalancer(LoadBalancerInterface $loadBalancer): TransporterInterface;
}