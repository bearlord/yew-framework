<?php
/**
 * Yew framework - Actor dispatcher (execution model) unit tests
 *
 * Pure PHP, no Swoole runtime required. Run directly:
 *   php vendor/bearlord/yew-framework/src/Plugins/Actor/test/Actor/DispatcherUnitTest.php
 */

namespace Yew\Plugins\Actor\test\Actor;

use Yew\Plugins\Actor\Dispatcher\CoroutineDispatcher;
use Yew\Plugins\Actor\Dispatcher\PinnedDispatcher;
use Yew\Plugins\Actor\Dispatcher\ThreadPoolDispatcher;

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

// Dummy pure compute task
$task = fn($x) => $x * $x;

$coro = new CoroutineDispatcher();
$pinned = new PinnedDispatcher();
$pool = new ThreadPoolDispatcher(2);

check('coroutine scheduleCpuBound computes', $coro->scheduleCpuBound($task, 4) === 16);
check('pinned scheduleCpuBound computes', $pinned->scheduleCpuBound($task, 5) === 25);
check('thread-pool scheduleCpuBound computes (degraded or real)', $pool->scheduleCpuBound($task, 6) === 36);

// Dispatch contract: every dispatcher exposes both methods (interface conformance)
foreach ([$coro, $pinned, $pool] as $d) {
    check('dispatcher implements dispatch+cheduleCpuBound', method_exists($d, 'dispatch') && method_exists($d, 'scheduleCpuBound'));
}

// Thread support probe (informational)
$threadSupported = class_exists(\Swoole\Thread::class) && method_exists(\Swoole\Thread::class, 'isSupported') && \Swoole\Thread::isSupported();
echo "INFO: Swoole\\Thread supported = " . var_export($threadSupported, true) . "\n";

echo "\nTotal: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
