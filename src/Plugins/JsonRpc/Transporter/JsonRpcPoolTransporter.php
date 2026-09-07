<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc\Transporter;

/**
 * Alias of JsonRpcTransporter for the "pool" protocol. Kept as a concrete
 * class so the interface contract holds and it can be referenced as a
 * transporter, while reusing the TCP transporter implementation.
 */
class JsonRpcPoolTransporter extends JsonRpcTransporter
{
}
