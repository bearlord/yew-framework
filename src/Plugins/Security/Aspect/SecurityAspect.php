<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security\Aspect;

use Yew\Core\DI\DI;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Security\AccessDeniedException;
use Yew\Plugins\Security\Annotation\PostAuthorize;
use Yew\Plugins\Security\Annotation\PreAuthorize;
use Yew\Plugins\Security\SecurityChecker;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Around;


class SecurityAspect extends OrderAspect
{
    /**
     * Resolve the checker from the annotation and run it.
     */
    private function authorize(MethodInvocation $invocation, string $class, string $value): bool
    {
        if (empty($class)) {
            throw new AccessDeniedException();
        }
        if (!class_exists($class) || !is_subclass_of($class, SecurityChecker::class)) {
            throw new AccessDeniedException();
        }

        /** @var SecurityChecker $checker */
        $checker = DI::getInstance()->get($class);
        if (!$checker instanceof SecurityChecker) {
            throw new AccessDeniedException();
        }

        return $checker->check($invocation, $value);
    }

    /**
     * @Around("@execution(Yew\Plugins\Security\Annotation\PostAuthorize)")
     */
    public function aroundPostAuthorize(MethodInvocation $invocation)
    {
        $postAuthorize = $invocation->getMethod()->getAnnotation(PostAuthorize::class);

        $returnObject = $invocation->proceed();

        if ($this->authorize($invocation, $postAuthorize->class, $postAuthorize->value ?? '')) {
            return $returnObject;
        }
        throw new AccessDeniedException();
    }

    /**
     * @Around("@execution(Yew\Plugins\Security\Annotation\PreAuthorize)")
     */
    public function aroundPreAuthorize(MethodInvocation $invocation)
    {
        $preAuthorize = $invocation->getMethod()->getAnnotation(PreAuthorize::class);

        if ($this->authorize($invocation, $preAuthorize->class, $preAuthorize->value ?? '')) {
            return $invocation->proceed();
        }
        throw new AccessDeniedException();
    }

    public function getName(): string
    {
        return "SecurityAspect";
    }
}
