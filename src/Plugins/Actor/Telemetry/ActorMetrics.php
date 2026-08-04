<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Telemetry;

use Swoole\Table;

/**
 * In-process metrics for an actor: message throughput, processing latency and
 * mailbox depth. Backed by a shared-memory Table so the data is observable
 * from any worker/process (e.g. an HTTP metrics endpoint or exporter).
 *
 * Mirrors the kind of counters an OpenTelemetry meter would produce; the
 * {@see ActorTelemetry} facade exposes them as a meter-like API.
 */
class ActorMetrics
{
    private Table $table;

    /**
     * Create the shared-memory metrics table.
     *
     * @param int $maxActors Max number of actors tracked concurrently
     */
    public function __construct(int $maxActors = 4096)
    {
        $this->table = new Table($maxActors);
        $this->table->column('processed', Table::TYPE_INT);   // total messages handled
        $this->table->column('errors', Table::TYPE_INT);      // failed handlers
        $this->table->column('latency_sum', Table::TYPE_FLOAT); // sum of seconds (avg)
        $this->table->column('latency_max', Table::TYPE_FLOAT);
        $this->table->column('mailbox_max', Table::TYPE_INT);  // observed max depth
        $this->table->create();
    }

    /**
     * Accumulate one processed message's counters for an actor.
     *
     * @param string $actorName Actor name (table key)
     * @param float $seconds Processing latency in seconds
     * @param int $mailboxDepth Mailbox depth observed during handling
     * @param bool $errored Whether the handler threw
     */
    public function recordProcess(string $actorName, float $seconds, int $mailboxDepth, bool $errored = false): void
    {
        $row = $this->table->get($actorName) ?: [
            'processed' => 0, 'errors' => 0, 'latency_sum' => 0.0,
            'latency_max' => 0.0, 'mailbox_max' => 0,
        ];
        $row['processed'] += 1;
        if ($errored) {
            $row['errors'] += 1;
        }
        $row['latency_sum'] += $seconds;
        $row['latency_max'] = max($row['latency_max'], $seconds);
        $row['mailbox_max'] = max($row['mailbox_max'], $mailboxDepth);
        $this->table->set($actorName, $row);
    }

    /**
     * Snapshot for a single actor.
     */
    public function snapshot(string $actorName): ?array
    {
        $row = $this->table->get($actorName);
        if ($row === false) {
            return null;
        }
        $row['avg_latency'] = $row['processed'] > 0 ? $row['latency_sum'] / $row['processed'] : 0.0;
        $row['actor'] = $actorName;

        return $row;
    }

    /**
     * All actor snapshots (for an exporter / debug endpoint).
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->table as $name => $row) {
            $row['avg_latency'] = $row['processed'] > 0 ? $row['latency_sum'] / $row['processed'] : 0.0;
            $row['actor'] = $name;
            $out[$name] = $row;
        }

        return $out;
    }
}
