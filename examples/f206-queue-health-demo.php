<?php
declare(strict_types=1);

/** F206: reproducible, redacted queue-health rehearsal. Pure in-memory PHP. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockOrchestration\{
    MockReadOnlyRunQueue, MockUncertaintyReviewBoard, MockQueueHealthSnapshot
};

try {
    $ctx = new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A',
        'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD');
    $q = new MockReadOnlyRunQueue($ctx);
    $board = new MockUncertaintyReviewBoard($ctx, $q);
    $health = new MockQueueHealthSnapshot($ctx, $board);
    $stages = [];
    $stages['idle'] = $health->assess($ctx);
    foreach (['A', 'B'] as $suffix) {
        $q->enqueue($ctx, 'SYNTHETIC_F206_RUN_' . $suffix,
            'SYNTHETIC_F206_REQ_' . $suffix, 10);
    }
    $stages['pending'] = $health->assess($ctx);
    $first = $q->claim($ctx, 11, 2);
    if ($first === null || $first['run_ref'] !== 'SYNTHETIC_F206_RUN_A') {
        throw new RuntimeException('F206_FIRST_CLAIM_DENIED');
    }
    $stages['reserved'] = $health->assess($ctx);
    $second = $q->claim($ctx, 13, 5);
    if ($second === null || $second['run_ref'] !== 'SYNTHETIC_F206_RUN_B') {
        throw new RuntimeException('F206_SECOND_CLAIM_DENIED');
    }
    $stages['uncertain'] = $health->assess($ctx);
    $q->pauseAdmission($ctx, 'SYNTHETIC_F206_PAUSE');
    foreach (['SYNTHETIC_REVIEWER_A', 'SYNTHETIC_REVIEWER_B'] as $reviewer) {
        $board->acknowledge($ctx, 'SYNTHETIC_F206_RUN_A',
            $reviewer, 'SYNTHETIC_TIMEOUT_OBSERVED');
    }
    $stages['paused'] = $health->assess($ctx);
    $q->resumeAdmission($ctx, 'SYNTHETIC_F206_RESUME');
    $q->finish($ctx, $second['run_ref'], $second['claim_ref'], 'CONCLUIDA', 14);
    $stages['resumed'] = $health->assess($ctx);

    // Separate, isolated synthetic queues for capacity boundary exercises.
    foreach ([60 => 'near_capacity', 64 => 'full_capacity'] as $size => $label) {
        $pressureQueue = new MockReadOnlyRunQueue($ctx);
        $pressureBoard = new MockUncertaintyReviewBoard($ctx, $pressureQueue);
        for ($i = 0; $i < $size; ++$i) {
            $pressureQueue->enqueue($ctx, 'SYNTHETIC_F206_CAP_RUN_' . $i,
                'SYNTHETIC_F206_CAP_REQ_' . $i, 10);
        }
        $stages[$label] = (new MockQueueHealthSnapshot($ctx, $pressureBoard))
            ->assess($ctx);
    }

    $expected = [
        'idle' => 'QUIET', 'pending' => 'PENDING',
        'reserved' => 'LEASE_IN_PROGRESS', 'uncertain' => 'REVIEW_REQUIRED',
        'paused' => 'PAUSED', 'resumed' => 'REVIEW_REQUIRED',
        'near_capacity' => 'CAPACITY_NEAR_LIMIT',
        'full_capacity' => 'CAPACITY_EXHAUSTED',
    ];
    foreach ($expected as $name => $classification) {
        $snapshot = $stages[$name];
        if ($snapshot['classification'] !== $classification
            || $snapshot['operational_decision'] !== 'NO_GO'
            || $snapshot['real_authorization'] !== false
            || $snapshot['external_calls'] !== 0
            || $snapshot['write_calls'] !== 0) {
            throw new RuntimeException('F206_HEALTH_INVARIANT_DENIED');
        }
    }
    if ($stages['resumed']['triage']['queue']['indeterminate'] !== 1
        || $stages['resumed']['triage']['review']['reviewed_unresolved'] !== 1
        || $stages['full_capacity']['capacity']['jobs']['remaining'] !== 0) {
        throw new RuntimeException('F206_UNCERTAINTY_OR_CAPACITY_DENIED');
    }
    echo json_encode([
        'contract' => 'SYNTHETIC_QUEUE_HEALTH_REHEARSAL_V1',
        'stages' => $stages,
        'operational_decision' => 'NO_GO',
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable) {
    fwrite(STDERR, "SYNTHETIC_F206_REHEARSAL_FAILED\n");
    exit(1);
}
