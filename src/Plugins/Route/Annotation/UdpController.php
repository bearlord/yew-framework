<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
class UdpController extends Controller
{
    /**
     * @var array
     */
    public array $portTypes = ["udp"];

    /**
     * @var string
     */
    public string $defaultMethod = "UDP";
}