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
class WsController extends Controller
{
    /**
     * @var array
     */
    public array $portTypes = ["ws"];

    /**
     * @var string
     */
    public string $defaultMethod = "WS";
}