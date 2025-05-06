<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\DI;

interface Factory
{
    /**
     * @param array|null $params
     * @return mixed
     */
    public function create(?array $params = null);
}