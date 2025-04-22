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
class MqttController extends Controller
{
    /**
     * @var array
     */
    public array $portTypes = ["mqtt"];

    /**
     * @var string
     */
    public string $defaultMethod = "tcp";
}