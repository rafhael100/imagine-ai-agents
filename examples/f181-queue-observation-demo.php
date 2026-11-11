<?php
declare(strict_types=1);

/**
 * F181 — fixed CLI demonstration of FIFO empty/active/uncertain states.
 * No argv, stdin, environment, HTTP, CRM connection, SQL or paid AI.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmOwnerPolicy, MockCrmReadPublicFacade};
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockTelemetry\{MockCrmReadEdgeBoundary, MockCrmReadEdgeCounters};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\MockOrchestration\{SupervisedCoordinator, MockReadOnlyRunQueue};
use ImagineAI\MockPilot\{MockReadOnlyPilot, MockQueuedReadOnlyPilot, MockQueuedBatchRunner};
use ImagineAI\Mocks\{MockCrmReader, MockSupervisedGateway, MockAiSuggestionProvider};

try {
    $context = new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A',
        'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD');
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($context, ['ref']);
    $audit = new MockCrmReadAuditTrail();
    $counters = new MockCrmReadEdgeCounters();
    $trust = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ']);
    $facade = new MockCrmReadPublicFacade($trust, new MockCrmReader(), $policy, $audit);
    $edge = new MockCrmReadEdgeBoundary($facade, $counters);
    $mutations = new MockSupervisedGateway();
    $coordinator = new SupervisedCoordinator($context, $mutations,
        new MockAiSuggestionProvider());
    $pilot = new MockReadOnlyPilot($context, $edge, $coordinator);
    $queue = new MockReadOnlyRunQueue($context);
    $runner = new MockQueuedReadOnlyPilot($context, $queue, $pilot);
    $batch = new MockQueuedBatchRunner($context, $runner);

    $envelope = new MockTrustEnvelope('SYNTHETIC_F181_ENVELOPE',
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', $context,
        'SYNTHETIC_F181_NONCE', ['SYNTHETIC_LEAD_READ'], 10, 40);
    $owner = new MockOwnerScopeEvidence('SYNTHETIC_F181_OWNER',
        $envelope->digest, $context, 'SYNTHETIC_LEAD_READ', 10, 40, true);
    $pair = ['envelope' => $envelope, 'owner' => $owner];

    // An actually empty queue has no reserved nor unresolved job.
    $empty = $runner->runNext($context, $envelope, $owner, ['ref'], 10, 5);

    // One claim is reserved, not yet completed; polling is not a new read.
    $queue->enqueue($context, 'SYNTHETIC_F181_RUN_A', 'SYNTHETIC_F181_REQ_A', 10);
    $queue->claim($context, 11, 3);
    $active = $runner->runNext($context, $envelope, $owner, ['ref'], 12, 5);
    $activeBatch = $batch->drain($context, [$pair], ['ref'], 12, 5);

    // Exact lease expiration makes the old job indeterminate, not EMPTY.
    $review = $runner->runNext($context, $envelope, $owner, ['ref'], 14, 5);
    $reviewBatch = $batch->drain($context, [$pair], ['ref'], 14, 5);

    // A separate pending Run may succeed without erasing the old uncertainty.
    $queue->enqueue($context, 'SYNTHETIC_F181_RUN_B', 'SYNTHETIC_F181_REQ_B', 14);
    $partial = $batch->drain($context, [
        $pair, ['envelope' => null, 'owner' => null],
    ], ['ref'], 15, 5);
    $finalCounts = $queue->counts();

    if ($empty['state'] !== 'EMPTY'
        || $active['state'] !== 'IN_PROGRESS'
        || $review['state'] !== 'REVIEW_REQUIRED'
        || $activeBatch['state'] !== 'IN_PROGRESS'
        || $reviewBatch['state'] !== 'REVIEW_REQUIRED'
        || $partial['state'] !== 'REVIEW_REQUIRED'
        || $partial['processed'] !== 1 || $partial['succeeded'] !== 1
        || $partial['denied'] !== 0
        || $activeBatch['processed'] !== 0
        || $reviewBatch['processed'] !== 0
        || $finalCounts['CONCLUIDA'] !== 1
        || $finalCounts['INDETERMINADO'] !== 1
        || $finalCounts['PENDENTE'] !== 0
        || $audit->count() !== 1
        || $counters->snapshot()['attempts'] !== 1
        || count($coordinator->events()) !== 4
        || $mutations->submitCount() !== 0
        || $mutations->statusCount() !== 0) {
        throw new RuntimeException('SYNTHETIC_F181_INVARIANT_DENIED');
    }

    // Output only categories and aggregate counts — never IDs/claims/nonces.
    echo json_encode([
        'demo' => 'SYNTHETIC_F181_QUEUE_STATES_DEMO_V1',
        'mode' => 'OFFLINE_SYNTHETIC_ONLY',
        'operational_decision' => 'NO_GO',
        'states' => [
            'no_job' => $empty['state'],
            'reserved' => $active['state'],
            'expired' => $review['state'],
            'batch_reserved' => $activeBatch['state'],
            'batch_expired' => $reviewBatch['state'],
            'partial_batch' => $partial['state'],
        ],
        'partial' => [
            'processed' => $partial['processed'],
            'succeeded' => $partial['succeeded'],
            'denied' => $partial['denied'],
        ],
        'queue' => [
            'completed' => $finalCounts['CONCLUIDA'],
            'indeterminate' => $finalCounts['INDETERMINADO'],
            'pending' => $finalCounts['PENDENTE'],
        ],
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable) {
    fwrite(STDERR, "SYNTHETIC_F181_DEMO_FAILED\n");
    exit(1);
}
