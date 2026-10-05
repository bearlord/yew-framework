<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Annotation;
use Attribute;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[Attribute(Attribute::TARGET_CLASS)]
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