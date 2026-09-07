<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc;

use Yew\Core\Exception;

class RpcException extends Exception
{
    public static function callFailed(string $service, string $method, ?Throwable $previous = null): self
    {
        return new self(sprintf('RPC call to %s::%s failed', $service, $method), 0, $previous);
    }
}