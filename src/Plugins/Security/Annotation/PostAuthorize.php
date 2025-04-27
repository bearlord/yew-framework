<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security\Annotation;


use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class PostAuthorize extends Annotation
{
    /**
     * @var string
     */
    public $value;
}