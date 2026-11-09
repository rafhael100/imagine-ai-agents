<?php
declare(strict_types=1);

namespace ImagineAI\MockTelemetry;

use ImagineAI\Domain\MockError;

/**
 * Aggregate counters for a synthetic in-memory edge only.
 * Never stores request bodies, tenant names, identifiers, credentials or timestamps.
 * This is neither a durable operational audit nor production monitoring.
 */
final class MockCrmReadEdgeCounters
{
    private int $started = 0;
    private array $pending = [];
    private array $counts = [
        'READ_SYNTHETIC_ONLY' => 0,
        'SCHEMA_DENIED' => 0,
        'DENIED_OR_NOT_FOUND' => 0,
        'INTERNAL_DENIED' => 0,
    ];

    public function __construct(private readonly int $capacity = 32)
    {
        if ($capacity < 1 || $capacity > 256) MockError::fail('EDGE_SCHEMA_DENIED');
    }

    /**
     * Reserve a recording slot before parsing or attempting any synthetic read.
     * Capacity exhaustion MUST prevent downstream work.
     */
    public function begin(): int
    {
        if ($this->started >= $this->capacity) MockError::fail('EDGE_CAPACITY_DENIED');
        $ticket = ++$this->started;
        $this->pending[$ticket] = true;
        return $ticket;
    }

    /** One completion only. No raw cause, request fingerprint or identifier permitted. */
    public function finish(int $ticket, string $outcome): void
    {
        if (!isset($this->pending[$ticket]) || !array_key_exists($outcome, $this->counts)) {
            MockError::fail('EDGE_SCHEMA_DENIED');
        }
        unset($this->pending[$ticket]);
        ++$this->counts[$outcome];
    }

    /** Value copy of fixed-cardinality counts only; no request-derived labels. */
    public function snapshot(): array
    {
        return [
            'contract' => 'SYNTHETIC_EDGE_COUNTERS_V1',
            'attempts' => $this->started,
            'pending' => count($this->pending),
            'outcomes' => $this->counts,
            'real_authorization' => false,
            'external_calls' => 0,
        ];
    }
}
