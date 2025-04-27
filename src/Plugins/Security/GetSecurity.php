<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Plugins\Security\Beans\Principal;
use Yew\Plugins\Session\GetSession;


trait GetSecurity
{
    use GetSession;

    /**
     * @return Principal|null
     */
    public function getPrincipal(): ?Principal
    {
        return $this->getSession()->getAttribute("Principal");
    }

    /**
     * @param Principal $principal
     */
    public function setPrincipal(Principal $principal)
    {
        $this->getSession()->setAttribute("Principal", $principal);
    }
}