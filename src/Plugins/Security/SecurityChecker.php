<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Goaop\Aop\Intercept\MethodInvocation;

/**
 * Contract for authorization checkers referenced by @PreAuthorize / @PostAuthorize.
 */
interface SecurityChecker
{
    /**
     * @return bool true = allow, false = deny
     */
    public function check(MethodInvocation $invocation, string $value = ''): bool;
}
