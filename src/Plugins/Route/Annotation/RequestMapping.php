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
 * @Target({"METHOD","CLASS"})
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RequestMapping extends Annotation
{
    /**
     * @var string
     */
    public $value;

    /**
     * @var array|null
     */
    public array $method = [];
}