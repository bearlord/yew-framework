<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Routing;

/**
 * Consistent hashing routing.
 *
 * Actors sharing the same routing key always land on the same worker process
 * (unless the process count changes), which keeps key-local state co-located
 * and improves cache locality / affinity.
 *
 * A virtual ring (default 128 replicas per node) minimises key reshuffling
 * when the worker count changes.
 */
class ConsistentHashStrategy implements ActorRoutingStrategy
{
    /**
     * Virtual nodes per physical process.
     *
     * @var int
     */
    private int $replicas;

    public function __construct(int $replicas = 128)
    {
        $this->replicas = $replicas;
    }

    public function select(int $processCount, ?string $routingKey = null): int
    {
        // Without a key we fall back to a hash of a random value so the
        // distribution still spreads. Callers using this strategy should pass
        // a stable routing key (e.g. actor name or tenant id).
        $key = $routingKey !== null && $routingKey !== '' ? $routingKey : (string) mt_rand();

        $targetHash = $this->hash($key);

        $bestIndex = 0;
        $bestDistance = null;

        for ($i = 0; $i < $processCount; $i++) {
            for ($r = 0; $r < $this->replicas; $r++) {
                $nodeHash = $this->hash("actor-$i#$r");
                // Clockwise distance on the ring [0, 2^32).
                $distance = ($nodeHash >= $targetHash)
                    ? ($nodeHash - $targetHash)
                    : ((0xFFFFFFFF - $targetHash) + $nodeHash);

                if ($bestDistance === null || $distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestIndex = $i;
                }
            }
        }

        return $bestIndex;
    }

    /**
     * 32-bit FNV-1a style hash mapped to unsigned int range.
     *
     * @param string $value
     * @return int
     */
    private function hash(string $value): int
    {
        $hash = 2166136261;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $hash ^= ord($value[$i]);
            $hash = ($hash * 16777619) & 0xFFFFFFFF;
        }

        return $hash;
    }
}
