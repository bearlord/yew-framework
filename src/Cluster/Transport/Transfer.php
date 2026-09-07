<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Transport;

/**
 * Inbound side of a transport: receives raw frames from the framework-managed
 * TCP listener ({@see \Yew\Cluster\Port\ClusterTcpPort}) and reacts to closes.
 *
 * Split from {@see RemoteTransport} so the listener can type-hint the handler
 * it needs without forcing every RemoteTransport (e.g. the in-process
 * {@see LocalTransport}) to expose fd-based framing methods it never uses.
 */
interface Transfer
{
    /**
     * A frame arrived on the given connection.
     *
     * @param int    $fd        Swoole connection file descriptor
     * @param string $data      Raw bytes for one envelope (already framed)
     */
    public function handleReceive(int $fd, string $data): void;

    /**
     * The given connection closed; drop any per-fd buffering/state.
     *
     * @param int $fd Swoole connection file descriptor
     */
    public function handleClose(int $fd): void;
}
