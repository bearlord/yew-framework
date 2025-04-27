<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;


class HttpSessionProxy
{
    use GetSession;

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getSession()->$name;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->getSession()->$name = $value;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getSession(), $name], $arguments);
    }
}