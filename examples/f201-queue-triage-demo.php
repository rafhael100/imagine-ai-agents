<?php
declare(strict_types=1);

/**
 * F201: deterministic, zero-network rehearsal of the F200 queue triage.
 * No tenant, run, claim, review, decision or nonce reference is emitted.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockOrchestration\{MockQueueTriage, MockReadOnlyRunQueue, MockUncertaintyReviewBoard};

try {
    $ctx = new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A',
        'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD');
    $queue = new MockReadOnlyRunQueue($ctx);
    $board = new MockUncertaintyReviewBoard($ctx, $queue);
    $triage = new MockQueueTriage($ctx, $board);
    $states = [];
    $states['empty'] = $triage->assess($ctx);
    foreach (['A', 'B'] as $id) {
        $queue->enqueue($ctx, 'SYNTHETIC_F201_RUN_' . $id,
            'SYNTHETIC_F201_REQ_' . $id, 10);
    }
    $states['pending'] = $triage->assess($ctx);
    $first = $queue->claim($ctx, 11, 2);
    if ($first === null || $first['run_ref'] !== 'SYNTHETIC_F201_RUN_A') {
        throw new RuntimeException('F201_FIRST_CLAIM_DENIED');
    }
    $states['leased'] = $triage->assess($ctx);
    $second = $queue->claim($ctx, 13, 5);
    if ($second === null || $second['run_ref'] !== 'SYNTHETIC_F201_RUN_B') {
        throw new RuntimeException('F201_SECOND_CLAIM_DENIED');
    }
    $states['uncertain'] = $triage->assess($ctx);
    $queue->pauseAdmission($ctx, 'SYNTHETIC_F201_PAUSE');
    $states['paused'] = $triage->assess($ctx);
    foreach (['SYNTHETIC_REVIEWER_A', 'SYNTHETIC_REVIEWER_B'] as $reviewer) {
        $board->acknowledge($ctx, 'SYNTHETIC_F201_RUN_A',
            $reviewer, 'SYNTHETIC_TIMEOUT_OBSERVED');
    }
    $queue->resumeAdmission($ctx, 'SYNTHETIC_F201_RESUME');
    $queue->finish($ctx, $second['run_ref'], $second['claim_ref'], 'CONCLUIDA', 14);
    $states['resumed'] = $triage->assess($ctx);

    $expected = [
        'empty' => 'QUIET', 'pending' => 'PENDING',
        'leased' => 'LEASE_IN_PROGRESS', 'uncertain' => 'REVIEW_REQUIRED',
        'paused' => 'PAUSED', 'resumed' => 'REVIEW_REQUIRED',
    ];
    foreach ($expected as $stage => $state) {
        if ($states[$stage]['state'] !== $state
            || $states[$stage]['operational_decision'] !== 'NO_GO'
            || $states[$stage]['real_authorization'] !== false
            || $states[$stage]['external_calls'] !== 0
            || $states[$stage]['write_calls'] !== 0) {
            throw new RuntimeException('F201_TRIAGE_STAGE_DENIED');
        }
    }
    if ($states['uncertain']['queue']['indeterminate'] !== 1
        || $states['resumed']['queue']['indeterminate'] !== 1
        || $states['resumed']['review']['reviewed_unresolved'] !== 1
        || $states['resumed']['queue']['completed'] !== 1
        || $states['resumed']['queue']['reserved'] !== 0) {
        throw new RuntimeException('F201_UNCERTAINTY_INVARIANT_DENIED');
    }
    $out = [
        'contract' => 'SYNTHETIC_QUEUE_TRIAGE_REHEARSAL_V1',
        'stages' => $states,
        'operational_decision' => 'NO_GO',
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ];
    echo json_encode($out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable) {
    fwrite(STDERR, "SYNTHETIC_F201_TRIAGE_FAILED\n");
    exit(1);
}
