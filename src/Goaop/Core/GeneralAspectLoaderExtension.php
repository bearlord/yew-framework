<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Yew\Goaop\Core;

use Yew\Goaop\Aop\Advisor;
use Yew\Goaop\Aop\Aspect;
use Yew\Goaop\Aop\Framework;
use Yew\Goaop\Aop\Pointcut;
use Yew\Goaop\Aop\Support\DefaultPointcutAdvisor;
use Yew\Goaop\Lang\Annotation;

/**
 * General aspect loader add common support for general advices, declared as annotations
 */
class GeneralAspectLoaderExtension extends AbstractAspectLoaderExtension
{

    /**
     * General aspect loader works with annotations from aspect
     *
     * For extension that works with annotations additional metaInformation will be passed
     *
     * @return string
     */
    public function getKind(): string
    {
        return self::KIND_ANNOTATION;
    }

    /**
     * General aspect loader works only with methods of aspect
     *
     * @return string
     */
    public function getTarget(): string
    {
        return self::TARGET_METHOD;
    }

    /**
     * Checks if loader is able to handle specific point of aspect
     *
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @return boolean true if extension is able to create an advisor from reflection and metaInformation
     */
    public function supports(Aspect $aspect, $reflection, $metaInformation = null)
    {
        return $metaInformation instanceof Annotation\Interceptor
                || $metaInformation instanceof Annotation\Pointcut;
    }

    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @return array|Pointcut[]|Advisor[]
     *
     * @throws \UnexpectedValueException
     */
    public function load(Aspect $aspect, $reflection, $metaInformation = null)
    {
        $loadedItems    = [];
        $pointcut       = $this->parsePointcut($aspect, $reflection, $metaInformation);
        $methodId       = get_class($aspect) . '->' . $reflection->name;
        $adviceCallback = $reflection->getClosure($aspect);

        switch (true) {
            // Register a pointcut by its name
            case ($metaInformation instanceof Annotation\Pointcut):
                $loadedItems[$methodId] = $pointcut;
                break;

            case ($pointcut instanceof Pointcut):
                $advice = $this->getInterceptor($metaInformation, $adviceCallback);

                $loadedItems[$methodId] = new DefaultPointcutAdvisor($pointcut, $advice);
                break;

            default:
                throw new \UnexpectedValueException('Unsupported pointcut class: ' . get_class($pointcut));
        }

        return $loadedItems;
    }

    /**
     * @param $metaInformation
     * @param $adviceCallback
     * @return Framework\AfterInterceptor|Framework\AfterThrowingInterceptor|Framework\AroundInterceptor|Framework\BeforeInterceptor
     */
    protected function getInterceptor($metaInformation, $adviceCallback)
    {
        $adviceOrder        = $metaInformation->order;
        $pointcutExpression = $metaInformation->value;
        switch (true) {
            case ($metaInformation instanceof Annotation\Before):
                return new Framework\BeforeInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof Annotation\After):
                return new Framework\AfterInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof Annotation\Around):
                return new Framework\AroundInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof Annotation\AfterThrowing):
                return new Framework\AfterThrowingInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            default:
                throw new \UnexpectedValueException('Unsupported method meta class: ' . get_class($metaInformation));
        }
    }
}
