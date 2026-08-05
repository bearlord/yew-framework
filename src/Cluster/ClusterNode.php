<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

/**
 * A single node in the actor cluster.
 *
 * In a single-machine Swoole deployment there is exactly one local node. When
 * clustering is added, each Swoole server instance becomes a node with a real
 * network address, and actors are sharded across them by a {@see ShardRouter}.
 */
class ClusterNode
{
    /**
     * @var string Stable node identifier (e.g. "node-1" or a uuid)
     */
    private string $nodeId;

    /**
     * @var string Host or ip; "127.0.0.1" for the local node
     */
    private string $host;

    /**
     * @var int Listening port; 0 when not network-reachable
     */
    private int $port;

    /**
     * @var bool True for the node this process belongs to
     */
    private bool $local;

    /**
     * Create a cluster node descriptor.
     *
     * @param string $nodeId Stable node id
     * @param string $host Host or ip ("127.0.0.1" for local)
     * @param int $port Listening port (0 when not network-reachable)
     * @param bool $local True for the node this process owns
     */
    public function __construct(string $nodeId, string $host = '127.0.0.1', int $port = 0, bool $local = true)
    {
        $this->nodeId = $nodeId;
        $this->host = $host;
        $this->port = $port;
        $this->local = $local;
    }

    /**
     * Stable node id.
     *
     * @return string
     */
    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    /**
     * Host or ip.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Listening port.
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Whether this is the local node.
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->local;
    }

    /**
     * Network endpoint string, e.g. "node-1@10.0.0.1:9501".
     */
    public function getEndpoint(): string
    {
        return sprintf('%s@%s:%d', $this->nodeId, $this->host, $this->port);
    }
}
