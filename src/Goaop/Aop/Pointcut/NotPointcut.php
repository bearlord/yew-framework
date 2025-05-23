<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Yew\Goaop\Aop\Pointcut;

use Yew\Goaop\Aop\Pointcut;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class NotPointcut implements Pointcut
{
    use PointcutClassFilterTrait;

    /**
     * @var Pointcut
     */
    protected $pointcut;

    /**
     * Kind of pointcut
     *
     * @var int
     */
    protected $kind = 0;

    /**
     * Inverse pointcut matcher
     *
     * @param Pointcut $pointcut Pointcut expression
     */
    public function __construct(Pointcut $pointcut)
    {
        $this->pointcut = $pointcut;
        $this->kind     = $pointcut->getKind();
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     * @param null|mixed $context Related context, can be class or namespace
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     *
     * @return bool
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null)
    {
        $isMatchesPre = $this->pointcut->getClassFilter()->matches($context);
        if (!$isMatchesPre) {
            return true;
        }
        $isMatchesPoint = $this->pointcut->matches($point, $context, $instance, $arguments);
        if (!$isMatchesPoint) {
            return true;
        }

        return false;
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return $this->kind;
    }
}
