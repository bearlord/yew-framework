<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Message;

/**
 * Contract for a message-type key used in the typed routing table.
 *
 * Implement this with a PHP 8 enum so message types become a closed,
 * compiler-checked set (no more free-form string literals):
 *
 *   enum OrderCommand: string implements MessageType {
 *       case Create = 'order.create';
 *       case Cancel = 'order.cancel';
 *       public function getName(): string { return $this->value; }
 *   }
 */
interface MessageType
{
    /**
     * Stable, unique name for this message type (used as routing-table key).
     */
    public function getName(): string;
}
