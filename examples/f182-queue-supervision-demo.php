<?php
declare(strict_types=1);

/**
 * F182: fixed, offline supervision snapshot built from synthetic queue state.
 * No HTTP, CRM, worker, database, credentials, billable AI or runtime input.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockOrchestration\{MockReadOnlyRunQueue, MockUncertaintyReviewBoard};

try {
    $ctx = new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A',
        'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD');
    $queue = new MockReadOnlyRunQueue($ctx);
    for ($i = 1; $i <= 4; ++$i) {
        $queue->enqueue($ctx, 'SYNTHETIC_F182_RUN_' . $i,
            'SYNTHETIC_F182_REQUEST_' . $i, 10);
    }
    for ($i = 0; $i < 3; ++$i) $queue->claim($ctx, 11, 2);
    $finalClaim = $queue->claim($ctx, 13, 5);
    if ($finalClaim === null
        || $finalClaim['run_ref'] !== 'SYNTHETIC_F182_RUN_4') {
        throw new RuntimeException('SYNTHETIC_F182_CLAIM_DENIED');
    }
    $queue->finish($ctx, $finalClaim['run_ref'], $finalClaim['claim_ref'],
        'CONCLUIDA', 14);

    $board = new MockUncertaintyReviewBoard($ctx, $queue);
    $board->acknowledge($ctx, 'SYNTHETIC_F182_RUN_1',
        'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
    $board->acknowledge($ctx, 'SYNTHETIC_F182_RUN_1',
        'SYNTHETIC_REVIEWER_B', 'SYNTHETIC_TIMEOUT_OBSERVED');
    $board->acknowledge($ctx, 'SYNTHETIC_F182_RUN_2',
        'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
    $board->acknowledge($ctx, 'SYNTHETIC_F182_RUN_2',
        'SYNTHETIC_REVIEWER_B', 'SYNTHETIC_RESULT_UNKNOWN');
    $board->acknowledge($ctx, 'SYNTHETIC_F182_RUN_3',
        'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_RESULT_UNKNOWN');

    $out = $board->overview($ctx);
    if ($out['queue'] !== [
            'pending' => 0, 'reserved' => 0, 'completed' => 1, 'failed' => 0,
            'cancelled' => 0, 'indeterminate' => 3,
        ] || $out['review'] !== [
            'awaiting_first' => 0, 'awaiting_second' => 1,
            'reviewed_unresolved' => 1, 'conflict' => 1,
        ] || count($board->events()) !== 5
        || $queue->counts()['INDETERMINADO'] !== 3
        || $queue->claim($ctx, 15, 5) !== null) {
        throw new RuntimeException('SYNTHETIC_F182_INVARIANT_DENIED');
    }
    echo json_encode($out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable) {
    fwrite(STDERR, "SYNTHETIC_F182_DEMO_FAILED\n");
    exit(1);
}
