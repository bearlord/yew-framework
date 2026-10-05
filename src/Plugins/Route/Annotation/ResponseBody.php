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
class ResponseBody extends Annotation
{
    /**
     * @var string
     */
    public $value = "application/json;charset=UTF-8";

    public string $xmlStartElement = "data";
}