<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;

/**
 * Lazy proxy that forwards property/method calls to the current HttpSession.
 */
class HttpSessionProxy
{
    use GetSession;

    public function __get($name)
    {
        return $this->getSession()->$name;
    }

    public function __set($name, $value)
    {
        $this->getSession()->$name = $value;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getSession(), $name], $arguments);
    }
}
