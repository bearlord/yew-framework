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
class PreAuthorize extends Annotation
{
    /**
     * Checker class (must implement Yew\Plugins\Security\SecurityChecker).
     * @var string
     */
    public $class;

    /**
     * Optional value passed through to the checker.
     * @var string
     */
    public $value = '';
}
