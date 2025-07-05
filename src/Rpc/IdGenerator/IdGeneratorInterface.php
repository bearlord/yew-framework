<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc\IdGenerator;

interface IdGeneratorInterface
{
    public function generate(): string;
}