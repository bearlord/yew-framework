<?php
/**
 * Yew framework - Actor routing strategy unit tests
 *
 * Pure PHP, no Swoole runtime required. Run directly:
 *   php vendor/bearlord/yew-framework/src/Plugins/Actor/test/Actor/RoutingUnitTest.php
 */

namespace Yew\Plugins\Actor\test\Actor;

use Yew\Plugins\Actor\Routing\ConsistentHashStrategy;
use Yew\Plugins\Actor\Routing\RoundRobinStrategy;
use Yew\Plugins\Actor\Routing\LeastLoadedStrategy;

$passed = 0;
$failed = 0;

function check(string $name, bool $cond): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "PASS: $name\n";
    } else {
        $failed++;
        echo "FAIL: $name\n";
    }
}

// --- RoundRobinStrategy (uses a dummy manager exposing getAtomic) ---
$manager = new class {
    private int $counter = 0;
    public function getAtomic(): object
    {
        return new class($this) {
            private $owner;
            public function __construct($owner) { $this->owner = $owner; }
            public function add(): int { return ++$this->owner->counter; }
        };
    }
    public int $counter = 0;
};

$rr = new RoundRobinStrategy($manager);
$seen = [];
for ($i = 0; $i < 6; $i++) {
    $seen[] = $rr->select(3);
}
check('round-robin cycles through processes', $seen === [1, 2, 0, 1, 2, 0]);

// --- ConsistentHashStrategy: same key -> same process ---
$ch = new ConsistentHashStrategy(128);
$procA = $ch->select(4, 'user-1001');
$procB = $ch->select(4, 'user-1001');
check('consistent hash is stable for same key', $procA === $procB);
check('consistent hash stays in range', $procA >= 0 && $procA < 4);

// Distribution sanity: keys should spread across processes
$buckets = array_fill(0, 4, 0);
for ($i = 0; $i < 400; $i++) {
    $buckets[$ch->select(4, "key-$i") % 4]++;
}
check('consistent hash spreads keys', max($buckets) < 400 && min($buckets) > 0);

// --- LeastLoadedStrategy (dummy manager with load table) ---
$loadState = [0 => 5, 1 => 2, 2 => 9, 3 => 2];
$llManager = new class($loadState) {
    public array $state;
    public function __construct(array $state) { $this->state = $state; }
    public function getLoad(int $i): int { return $this->state[$i] ?? 0; }
};
$ll = new LeastLoadedStrategy($llManager);
$chosen = $ll->select(4);
check('least-loaded picks a lightest process', $chosen === 1 || $chosen === 3);

echo "\nTotal: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
