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
class RestController extends Controller
{
    /**
     * @var array
     */
    public array $portTypes = ["http"];

    /**
     * @var string
     */
    public string $defaultMethod = "GET";
}