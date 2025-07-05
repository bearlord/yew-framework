<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc\IdGenerator;

class RequestIdGenerator implements IdGeneratorInterface
{
    public function generate(): string
    {
        $us = strstr(microtime(), ' ', true);
        return strval($us * 1000 * 1000) . rand(100, 999);
    }
}