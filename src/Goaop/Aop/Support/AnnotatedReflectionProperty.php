<?php

declare(strict_types = 1);
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
use ReflectionProperty;

/**
 * Extended version of ReflectionProperty with annotation support
 */
class AnnotatedReflectionProperty extends ReflectionProperty implements AnnotationAccess
{
    /**
     * Annotation reader
     */
    private static ?Reader $annotationReader = null;

    /**
     * Gets concrete annotation by name or null if the requested annotation does not exist.
     */
    public function getAnnotation(string $annotationName): ?object
    {
        return self::getReader()->getPropertyAnnotation($this, $annotationName);
    }

    /**
     * Gets all annotations applied to the current item.
     */
    public function getAnnotations(): array
    {
        return self::getReader()->getPropertyAnnotations($this);
    }

    /**
     * Returns an annotation reader
     */
    private static function getReader(): Reader
    {
        if (self::$annotationReader === null) {
            self::$annotationReader = AspectKernel::getInstance()->getContainer()->get('aspect.annotation.reader');
        }

        return self::$annotationReader;
    }
}
