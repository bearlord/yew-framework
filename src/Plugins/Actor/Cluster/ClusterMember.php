<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Cluster;

/**
 * Mutable runtime state of a single cluster node, mirrored in the shared-memory
 * membership table.
 *
 * Kept deliberately small and serialisable so the same struct can later be
 * shipped over a real gossip transport (UDP/TCP) instead of a Swoole Table.
 */
class ClusterMember
{
    public const STATUS_UP = 'up';
    public const STATUS_DOWN = 'down';
    public const STATUS_SUSPECT = 'suspect';

    /**
     * Create a cluster member record.
     *
     * @param string $nodeId Stable node identifier
     * @param string $host Host or ip
     * @param int $port Listening port
     * @param int $weight Relative scheduling weight
     * @param string $status One of STATUS_* constants
     * @param int $lastHeartbeat Unix time of last heartbeat
     * @param int $incarnation Bump-on-conflict counter
     * @param string $publicKey PEM public key for signature verification
     */
    public function __construct(
        public string $nodeId,
        public string $host,
        public int $port,
        public int $weight = 1,
        public string $status = self::STATUS_UP,
        public int $lastHeartbeat = 0,
        public int $incarnation = 0,
        /**
         * PEM-encoded public key of this node. Carried in gossip SYNC/SYN-ACK so
         * peers can learn it and verify this node's signed messages. Empty for
         * nodes discovered before public-key support.
         */
        public string $publicKey = ''
    ) {
    }

    /**
     * Whether the member is currently reachable (UP).
     *
     * @return bool
     */
    public function isAlive(): bool
    {
        return $this->status === self::STATUS_UP;
    }

    /**
     * Human-readable endpoint "nodeId@host:port".
     *
     * @return string
     */
    public function endpoint(): string
    {
        return sprintf('%s@%s:%d', $this->nodeId, $this->host, $this->port);
    }

    /**
     * Plain-array projection for Table storage (Swoole Table only holds scalars).
     */
    public function toRow(): array
    {
        return [
            'nodeId' => $this->nodeId,
            'host' => $this->host,
            'port' => $this->port,
            'weight' => $this->weight,
            'status' => $this->status,
            'lastHeartbeat' => $this->lastHeartbeat,
            'incarnation' => $this->incarnation,
            'publicKey' => $this->publicKey,
        ];
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['nodeId'],
            $row['host'],
            (int) $row['port'],
            (int) ($row['weight'] ?? 1),
            $row['status'],
            (int) ($row['lastHeartbeat'] ?? 0),
            (int) ($row['incarnation'] ?? 0),
            (string) ($row['publicKey'] ?? '')
        );
    }
}
