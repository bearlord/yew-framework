<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Yew\Goaop\Lang\Annotation;

/**
 * Default interceptor class with common attributes
 */
class BaseInterceptor extends BaseAnnotation implements Interceptor
{
    /**
     * Order for advice
     *
     * @var integer
     */
    public int $order = 0;
}
