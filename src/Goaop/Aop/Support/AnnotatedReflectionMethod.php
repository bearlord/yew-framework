<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Yew\Goaop\Aop\Support;

use Doctrine\Common\Annotations\Reader;
use Yew\Goaop\Core\AspectKernel;
use ReflectionMethod;

/**
 * Extended version of ReflectionMethod with annotation support
 */
class AnnotatedReflectionMethod extends ReflectionMethod
{
    /**
     * Annotation reader
     *
     * @var Reader
     */
    private static Reader $annotationReader;

    /**
     * Gets a method annotation.
     *
     * @param string $annotationName The name of the annotation.
     * @return mixed The Annotation or NULL, if the requested annotation does not exist.
     */
    public function getAnnotation(string $annotationName)
    {
        return self::getReader()->getMethodAnnotation($this, $annotationName);
    }

    /**
     * Gets the annotations applied to a method.
     *
     * @return array An array of Annotations.
     */
    public function getAnnotations(): array
    {
        return self::getReader()->getMethodAnnotations($this);
    }

    /**
     * Returns an annotation reader
     *
     * @return Reader $reader
     */
    private static function getReader(): Reader
    {
        if (!self::$annotationReader) {
            self::$annotationReader = AspectKernel::getInstance()->getContainer()->get('aspect.annotation.reader');
        }

        return self::$annotationReader;
    }
}
