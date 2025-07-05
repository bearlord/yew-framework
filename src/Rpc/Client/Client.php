<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc\Client;

abstract class Client
{
    abstract public function send($data);
}