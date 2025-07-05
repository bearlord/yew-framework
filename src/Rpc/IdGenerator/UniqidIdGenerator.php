<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc\IdGenerator;

class UniqidIdGenerator implements IdGeneratorInterface
{

    public function generate(): string
    {
        return uniqid();
    }

}
