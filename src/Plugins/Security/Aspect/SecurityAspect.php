<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security\Aspect;

use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Security\AccessDeniedException;
use Yew\Plugins\Security\Annotation\PostAuthorize;
use Yew\Plugins\Security\Annotation\PreAuthorize;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Around;


class SecurityAspect extends OrderAspect
{
    /**
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("@execution(Yew\Plugins\Security\Annotation\PostAuthorize)")
     * @return mixed
     * @throws AccessDeniedException
     */
    public function aroundPostAuthorize(MethodInvocation $invocation)
    {
        $postAuthorize = $invocation->getMethod()->getAnnotation(PostAuthorize::class);

        $p = $invocation->getArguments();

        $returnObject = $invocation->proceed();

        $ex = eval("return ($postAuthorize->value);");
        if ($ex) {
            return $returnObject;
        } else {
            throw new AccessDeniedException();
        }
    }

    /**
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("@execution(Yew\Plugins\Security\Annotation\PreAuthorize)")
     * @return mixed
     * @throws AccessDeniedException
     */
    public function aroundPreAuthorize(MethodInvocation $invocation)
    {
        $preAuthorize = $invocation->getMethod()->getAnnotation(PreAuthorize::class);

        $p = $invocation->getArguments();

        $ex = eval("return ($preAuthorize->value);");
        if ($ex) {
            return $invocation->proceed();
        } else {
            throw new AccessDeniedException();
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "SecurityAspect";
    }
}