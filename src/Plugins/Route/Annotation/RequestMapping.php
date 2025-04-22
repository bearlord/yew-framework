<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"METHOD","CLASS"})
 */
class RequestMapping extends Annotation
{
    /**
     * @var string
     */
    public $value;

    /**
     * @var array
     */
    public $method = [];
}