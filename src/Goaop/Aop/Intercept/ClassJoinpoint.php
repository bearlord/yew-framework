<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2019, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Yew\Goaop\Aop\Intercept;

/**
 * This interface represents a class joinpoint that can have $this variable and defined scope
 *
 * @api
 */
interface ClassJoinpoint extends Joinpoint
{
    /**
     * Checks if the current joinpoint is dynamic or static
     *
     * Dynamic joinpoint contains a reference to an object that can be received via getThis() method call
     *
     * @see ClassJoinpoint::getThis()
     *
     * @api
     */
    public function isDynamic(): bool;

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @return object|null Instance of object or null for static call/unavailable context
     *
     * @api
     */
    public function getThis(): ?object;

    /**
     * Returns the static scope name (class name) of this joinpoint.
     *
     * @api
     */
    public function getScope(): string;
}
