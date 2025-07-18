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
 * Declare parents annotation
 *
 * @Annotation
 * @Target("PROPERTY")
 *
 * @Attributes({
 *   @Attribute("value", type = "string", required=true),
 *   @Attribute("interface", type = "string"),
 *   @Attribute("defaultImpl", type = "string")
 * })
 */
class DeclareParents extends BaseAnnotation
{
    /**
     * Default implementation (trait name)
     *
     * @var string
     */
    public string $defaultImpl;

    /**
     * Interface name to add
     *
     * @var string
     */
    public string $interface;
}
