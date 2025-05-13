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
class PathVariable extends Annotation
{
    /**
     * @var string
     */
    public $value;

    /**
     * @var string|null
     */
    public ?string $param;

    /**
     * @var bool
     */
    public bool $required = false;
}