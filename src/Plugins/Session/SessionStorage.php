<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;

interface SessionStorage
{
    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id);

    /**
     * @param string $id
     * @param string $data
     * @return mixed
     */
    public function set(string $id,string $data);

    /**
     * @param string $id
     * @return mixed
     */
    public function remove(string $id);
}