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