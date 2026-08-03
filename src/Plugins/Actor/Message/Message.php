<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Message;

/**
 * Base contract for every strongly-typed message.
 *
 * A typed message knows its own {@see MessageType}, which the actor uses to
 * dispatch to a registered handler. This replaces the previous weakly-typed
 * "data bag" convention with a compiler-friendly, self-describing message.
 */
interface Message
{
    /**
     * The message type used for routing. Implementations typically return a
     * case of a PHP 8 enum that implements {@see MessageType}.
     */
    public function type(): MessageType;
}
