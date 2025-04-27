<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;


trait GetSession
{
    public function getSession(): HttpSession
    {
        $session = getDeepContextValueByClassName(HttpSession::class);
        if ($session == null) {
            $session = new HttpSession();
        }
        return $session;
    }
}