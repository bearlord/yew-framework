<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Annotation;
use Attribute;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
#[Attribute(Attribute::TARGET_METHOD)]
class GetMapping extends RequestMapping
{
    /**
     * @var array|null
     */
    public array $method = ["get"];
}