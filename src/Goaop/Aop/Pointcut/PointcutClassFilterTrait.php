<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Yew\Goaop\Aop\Pointcut;

use Yew\Goaop\Aop\PointFilter;
use Yew\Goaop\Aop\Support\TruePointFilter;

/**
 * Convenient trait for pointcuts.
 *
 * The "classFilter" property can be set to customize ClassFilter behavior.
 */
trait PointcutClassFilterTrait
{

    /**
     * Filter for class
     *
     * @var null|PointFilter
     */
    protected $classFilter;

    /**
     * Set the ClassFilter to use for this pointcut.
     *
     * @param PointFilter $classFilter
     */
    public function setClassFilter(PointFilter $classFilter)
    {
        $this->classFilter = $classFilter;
    }

    /**
     * Return the class filter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter()
    {
        if (!$this->classFilter) {
            $this->classFilter = TruePointFilter::getInstance();
        }

        return $this->classFilter;
    }
}
