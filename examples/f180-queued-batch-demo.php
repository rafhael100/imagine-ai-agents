<?php
declare(strict_types=1);

/**
 * Zero-input, no-network demonstration of the queued OP-01 batch.
 * This is NOT a real agent worker, CRM connector or authenticated service.
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

function f180Proof(TenantContext $context, string $ordinal): array
{
    $envelope = new MockTrustEnvelope(
        'SYNTHETIC_BATCH_ENVELOPE_' . $ordinal,
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', $context,
        'SYNTHETIC_BATCH_NONCE_' . $ordinal,
        ['SYNTHETIC_LEAD_READ'], 10, 40,
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_BATCH_OWNER_' . $ordinal, $envelope->digest,
        $context, 'SYNTHETIC_LEAD_READ', 10, 40, true,
    );
    return ['envelope' => $envelope, 'owner' => $owner];
}

try {
    // Fixed test context. No environment variables, user input or secrets.
    $context = new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A',
        'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD');
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($context, ['ref']);
    $audit = new MockCrmReadAuditTrail();
    $counters = new MockCrmReadEdgeCounters();
    $trust = new MockTrustGateway(
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ']);
    $facade = new MockCrmReadPublicFacade(
        $trust, new MockCrmReader(), $policy, $audit);
    $edge = new MockCrmReadEdgeBoundary($facade, $counters);
    $mutations = new MockSupervisedGateway();
    $coord = new SupervisedCoordinator(
        $context, $mutations, new MockAiSuggestionProvider());
    $pilot = new MockReadOnlyPilot($context, $edge, $coord);
    $queue = new MockReadOnlyRunQueue($context);
    $queued = new MockQueuedReadOnlyPilot($context, $queue, $pilot);
    $batch = new MockQueuedBatchRunner($context, $queued);

    $queue->enqueue($context, 'SYNTHETIC_BATCH_RUN_A', 'SYNTHETIC_BATCH_REQ_A', 10);
    $queue->enqueue($context, 'SYNTHETIC_BATCH_RUN_B', 'SYNTHETIC_BATCH_REQ_B', 10);
    $queue->enqueue($context, 'SYNTHETIC_BATCH_RUN_C', 'SYNTHETIC_BATCH_REQ_C', 10);
    $out = $batch->drain($context, [
        f180Proof($context, 'A'),
        f180Proof($context, 'B'),
        ['envelope' => null, 'owner' => null],
    ], ['ref'], 15, 5);

    $counts = $queue->counts();
    if ($out !== [
            'contract' => 'SYNTHETIC_QUEUED_BATCH_V1',
            'state' => 'BATCH_SYNTHETIC_ONLY',
            'processed' => 3, 'succeeded' => 2, 'denied' => 1,
            'real_authorization' => false, 'external_calls' => 0, 'write_calls' => 0,
        ]
        || $counts['CONCLUIDA'] !== 2 || $counts['FALHOU'] !== 1
        || $counts['PENDENTE'] !== 0
        || $audit->count() !== 3
        || $counters->snapshot()['attempts'] !== 3
        || count($coord->events()) !== 8
        || $mutations->submitCount() !== 0
        || $mutations->statusCount() !== 0) {
        throw new RuntimeException('SYNTHETIC_BATCH_INVARIANT_DENIED');
    }

    // Only category counts leave this fixed public example. Never reveal
    // principal, resource, nonce, proof, claim, raw tool fields or errors.
    echo json_encode([
        'demo' => 'SYNTHETIC_F180_QUEUED_BATCH_DEMO_V1',
        'mode' => 'OFFLINE_SYNTHETIC_ONLY',
        'operational_decision' => 'NO_GO',
        'batch' => $out,
        'queue' => ['completed' => $counts['CONCLUIDA'],
            'failed' => $counts['FALHOU'], 'pending' => $counts['PENDENTE']],
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable) {
    fwrite(STDERR, "SYNTHETIC_BATCH_DEMO_FAILED\n");
    exit(1);
}
