<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Plugins\Security\Beans\Principal;
use Yew\Plugins\Session\HttpSession;

trait GetSecurity
{
    /**
     * @param string $name
     * @param Principal $principal
     */
    public function setPrinciple($name, Principal $principal)
    {
        $session = getDeepContextValueByClassName(HttpSession::class);
        if ($session == null) {
            $session = new HttpSession();
        }
        $session->setAttribute($name, $principal);
    }

    /**
     * @param string $name
     * @return Principal|null
     */
    public function getPrinciple($name): ?Principal
    {
        $session = getDeepContextValueByClassName(HttpSession::class);
        if ($session == null) {
            $session = new HttpSession();
        }
        $principal = $session->getAttribute($name);
        return $principal instanceof Principal ? $principal : null;
    }
}
