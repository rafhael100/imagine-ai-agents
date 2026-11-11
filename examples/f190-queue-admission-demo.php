<?php
declare(strict_types=1);

/**
 * F190: deterministic, offline pause/resume rehearsal of the exact F189
 * redacted supervision contract, with no worker, CRM, network or secrets.
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
    $q = new MockReadOnlyRunQueue($ctx);
    $q->enqueue($ctx, 'SYNTHETIC_F190_RUN_A', 'SYNTHETIC_F190_REQ_A', 10);
    $q->enqueue($ctx, 'SYNTHETIC_F190_RUN_B', 'SYNTHETIC_F190_REQ_B', 10);
    $first = $q->claim($ctx, 11, 2);
    if ($first === null || $first['run_ref'] !== 'SYNTHETIC_F190_RUN_A') {
        throw new RuntimeException('SYNTHETIC_F190_FIRST_CLAIM_DENIED');
    }
    $q->pauseAdmission($ctx, 'SYNTHETIC_F190_PAUSE_1');
    if ($q->claim($ctx, 13, 5) !== null
        || $q->counts()['INDETERMINADO'] !== 1
        || $q->counts()['PENDENTE'] !== 1) {
        throw new RuntimeException('SYNTHETIC_F190_PAUSED_ADMISSION_DENIED');
    }

    $board = new MockUncertaintyReviewBoard($ctx, $q);
    foreach (['SYNTHETIC_REVIEWER_A', 'SYNTHETIC_REVIEWER_B'] as $reviewer) {
        $board->acknowledge($ctx, 'SYNTHETIC_F190_RUN_A', $reviewer,
            'SYNTHETIC_TIMEOUT_OBSERVED');
    }
    $paused = $board->overview($ctx);
    if ($paused['admission'] !== ['state' => 'PAUSED']
        || $paused['queue']['indeterminate'] !== 1
        || $paused['queue']['pending'] !== 1
        || $paused['review']['reviewed_unresolved'] !== 1) {
        throw new RuntimeException('SYNTHETIC_F190_PAUSED_REPORT_DENIED');
    }

    $q->resumeAdmission($ctx, 'SYNTHETIC_F190_RESUME_1');
    $next = $q->claim($ctx, 14, 5);
    if ($next === null || $next['run_ref'] !== 'SYNTHETIC_F190_RUN_B') {
        throw new RuntimeException('SYNTHETIC_F190_RESUME_CLAIM_DENIED');
    }
    $q->finish($ctx, $next['run_ref'], $next['claim_ref'], 'CONCLUIDA', 15);
    $resumed = $board->overview($ctx);
    if ($resumed['admission'] !== ['state' => 'OPEN']
        || $resumed['queue']['indeterminate'] !== 1
        || $resumed['queue']['completed'] !== 1
        || $resumed['queue']['pending'] !== 0
        || $resumed['review']['reviewed_unresolved'] !== 1) {
        throw new RuntimeException('SYNTHETIC_F190_RESUMED_REPORT_DENIED');
    }
    $eventCodes = array_column($q->events(), 'code');
    if (count(array_filter($eventCodes, static fn (string $c): bool =>
            $c === 'SYNTHETIC_ADMISSION_PAUSED')) !== 1
        || count(array_filter($eventCodes, static fn (string $c): bool =>
            $c === 'SYNTHETIC_ADMISSION_RESUMED')) !== 1
        || $q->counts()['INDETERMINADO'] !== 1
        || count($board->events()) !== 2) {
        throw new RuntimeException('SYNTHETIC_F190_AUDIT_DENIED');
    }
    $result = [
        'contract' => 'SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1',
        'paused' => $paused, 'resumed' => $resumed,
        'operational_decision' => 'NO_GO',
        'real_authorization' => false,
        'external_calls' => 0, 'write_calls' => 0,
    ];
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable) {
    fwrite(STDERR, "SYNTHETIC_F190_DEMO_FAILED\n");
    exit(1);
}
