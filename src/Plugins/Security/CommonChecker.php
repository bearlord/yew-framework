<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Plugins\Security\Beans\Principal;
use Yew\Plugins\Session\HttpSession;

/**
 * Base checker that pulls the Principal from the session.
 * Subclass and override check() for custom rules.
 */
class CommonChecker implements SecurityChecker
{
    protected function getPrincipal(): ?Principal
    {
        $session = getDeepContextValueByClassName(HttpSession::class);
        if ($session == null) {
            $session = new HttpSession();
        }
        $principal = $session->getAttribute("Principal");
        return $principal instanceof Principal ? $principal : null;
    }

    protected function isAuthenticated(): bool
    {
        return $this->getPrincipal() !== null;
    }

    protected function hasAnyRoleFromString(string $value): bool
    {
        $roles = array_filter(array_map('trim', explode(',', $value)));
        $principal = $this->getPrincipal();
        if ($principal === null || empty($roles)) {
            return false;
        }
        return $principal->hasAnyRole($roles);
    }

    protected function hasAnyPermissionFromString(string $value): bool
    {
        $permissions = array_filter(array_map('trim', explode(',', $value)));
        $principal = $this->getPrincipal();
        if ($principal === null || empty($permissions)) {
            return false;
        }
        foreach ($permissions as $permission) {
            if ($principal->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Built-in routing: value supports "role:..", "perm:.." or "authenticated".
     */
    public function check(MethodInvocation $invocation, string $value = ''): bool
    {
        if ($value === '' || $value === 'authenticated') {
            return $this->isAuthenticated();
        }
        if (strpos($value, 'role:') === 0) {
            return $this->hasAnyRoleFromString(substr($value, 5));
        }
        if (strpos($value, 'perm:') === 0) {
            return $this->hasAnyPermissionFromString(substr($value, 5));
        }
        return false;
    }
}
