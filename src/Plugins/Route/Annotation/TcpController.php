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
class TcpController extends Controller
{
    /**
     * @var array
     */
    public array $portTypes = ["tcp"];

    /**
     * @var string
     */
    public string $defaultMethod = "TCP";
}