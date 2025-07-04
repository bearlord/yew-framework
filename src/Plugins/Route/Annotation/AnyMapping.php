<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class AnyMapping extends RequestMapping
{
    /**
     * @var array|null
     */
    public array $method = ["get","post","delete","put","options","head","trace","connect"];
}