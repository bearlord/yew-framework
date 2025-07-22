<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp;

class Result
{
    /**
     * Acknowledge the message.
     */
    const ACK = "ack";

    /**
     * Unacknowledge the message.
     */
    const NACK = "nack";

    /**
     * Reject the message and requeue it.
     */
    const REQUEUE = "requeue";

    /**
     * Reject the message and drop it.
     */
    const DROP = "drop";
}