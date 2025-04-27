<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Core\Server\Beans\Request;
use Yew\Plugins\Security\Beans\Principal;
use Yew\Plugins\Session\HttpSession;

/**
 * @param string $role
 * @return bool
 */
function hasRole(string $role): bool
{
    $session = getDeepContextValueByClassName(HttpSession::class);
    if ($session == null) {
        $session = new HttpSession();
    }
    $principal = $session->getAttribute("Principal");
    if ($principal instanceof Principal) {
        return $principal->hasRole($role);
    } else {
        return false;
    }
}

/**
 * @param array $roles
 * @return bool
 */
function hasAnyRole(array $roles): bool
{
    $session = getDeepContextValueByClassName(HttpSession::class);
    if ($session == null) {
        $session = new HttpSession();
    }
    $principal = $session->getAttribute("Principal");
    if ($principal instanceof Principal) {
        return $principal->hasAnyRole($roles);
    } else {
        return false;
    }
}

/**
 *
 * @return bool
 */
function permitAll(): bool
{
    return true;
}

/**
 *
 * @return bool
 */
function denyAll(): bool
{
    return false;
}


/**
 * @return bool
 */
function isAuthenticated(): bool
{
    $session = getDeepContextValueByClassName(HttpSession::class);
    if ($session == null) {
        $session = new HttpSession();
    }
    $principal = $session->getAttribute("Principal");
    if ($principal == null) {
        return false;
    } else {
        return true;
    }
}

/**
 * @param string $permission
 * @return bool
 */
function hasPermission(string $permission)
{
    $session = getDeepContextValueByClassName(HttpSession::class);
    if ($session == null) {
        $session = new HttpSession();
    }
    $principal = $session->getAttribute("Principal");
    if ($principal instanceof Principal) {
        return $principal->hasPermission($permission);
    } else {
        return false;
    }
}

/**
 * @param $ips
 * @return bool
 */
function hasIpAddress($ips): bool
{
    $request = getDeepContextValueByClassName(Request::class);
    if ($request instanceof Request) {
        $ip = $request->getServer(Request::SERVER_REMOTE_ADDR);
        if (is_array($ips)) {
            foreach ($ips as $oneip) {
                if ($oneip == $ip) {
                    return true;
                }

                $exip = explode("/", $oneip);
                $mask = $exip[1] ?? null;
                if ($mask != null) {
                    if (netMatch($ip, $exip[0], $mask)) return true;
                }
            }
            return false;
        } elseif (is_string($ips)) {
            if ($ips == $ip) {
                return true;
            }

            $exip = explode("/", $ips);
            $mask = $exip[1] ?? null;
            if ($mask != null) {
                return netMatch($ip, $exip[0], $mask);
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }

}

function netMatch($client_ip, $server_ip, $mask): bool
{
    $mask1 = 32 - $mask;
    return ((ip2long($client_ip) >> $mask1) == (ip2long($server_ip) >> $mask1));
}