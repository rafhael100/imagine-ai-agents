<?php
declare(strict_types=1);

/**
 * F218: actual queue → pilot → edge receipt lifetime in isolated PHP mocks.
 * No HTTP, service identity, CRM, customer data, credentials or worker.
 */
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockBridge\MockCrmOwnerPolicy;
use ImagineAI\MockBridge\MockCrmReadPublicFacade;
use ImagineAI\MockBridge\MockCrmReadRequestBuilder;
use ImagineAI\MockOrchestration\MockReadOnlyRunQueue;
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockPilot\MockReadOnlyPilot;
use ImagineAI\MockPilot\MockQueuedReadOnlyPilot;
use ImagineAI\MockPilot\MockQueuedBatchRunner;
use ImagineAI\MockTelemetry\MockCrmReadEdgeBoundary;
use ImagineAI\MockTelemetry\MockCrmReadEdgeCounters;
use ImagineAI\MockTrust\MockOwnerScopeEvidence;
use ImagineAI\MockTrust\MockTrustEnvelope;
use ImagineAI\MockTrust\MockTrustGateway;
use ImagineAI\Mocks\MockAiSuggestionProvider;
use ImagineAI\Mocks\MockCrmReader;
use ImagineAI\Mocks\MockSupervisedGateway;

spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function f218Check(bool $ok): void
{
    if (!$ok) throw new RuntimeException('F218_QUEUE_RECEIPT_DENIED');
}
function f218Proof(TenantContext $ctx, string $suffix): array
{
    $envelope = new MockTrustEnvelope(
        'SYNTHETIC_F218_ENV_' . $suffix,
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        $ctx, 'SYNTHETIC_F218_NONCE_' . $suffix,
        ['SYNTHETIC_LEAD_READ'], 10, 40
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_F218_OWNER_' . $suffix,
        $envelope->digest, $ctx, 'SYNTHETIC_LEAD_READ',
        10, 40, true
    );
    return [$envelope, $owner];
}
function f218QueuedDenial(): array
{
    return [
        'contract' => 'SYNTHETIC_QUEUED_PILOT_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'summary' => null,
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ];
}
function f218BatchDenial(): array
{
    return [
        'contract' => 'SYNTHETIC_QUEUED_BATCH_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'processed' => 0,
        'succeeded' => 0,
        'denied' => 0,
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ];
}

try {
    $ctx = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A',
        'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY',
        'SYNTHETIC_A_LEAD'
    );
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($ctx, ['ref']);
    $audit = new MockCrmReadAuditTrail();
    $counts = new MockCrmReadEdgeCounters();
    $facade = new MockCrmReadPublicFacade(
        new MockTrustGateway(
            'SYNTHETIC_AGENT_SERVICE',
            'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ']
        ),
        new MockCrmReader(), $policy, $audit
    );
    $edge = new MockCrmReadEdgeBoundary($facade, $counts);
    $effects = new MockSupervisedGateway();
    $coord = new SupervisedCoordinator(
        $ctx, $effects, new MockAiSuggestionProvider()
    );
    $pilot = new MockReadOnlyPilot($ctx, $edge, $coord);
    $queue = new MockReadOnlyRunQueue($ctx);
    $queued = new MockQueuedReadOnlyPilot($ctx, $queue, $pilot);
    $batch = new MockQueuedBatchRunner($ctx, $queued);

    // Success and a legitimate recheck of exactly this queued read.
    $queue->enqueue($ctx, 'SYNTHETIC_F218_RUN_A',
        'SYNTHETIC_F218_REQUEST_A', 15);
    [$envA, $ownerA] = f218Proof($ctx, 'A');
    $wireA = MockCrmReadRequestBuilder::fromContext(
        $ctx, 'SYNTHETIC_F218_REQUEST_A', ['ref']);
    f218Check($queued->runNext($ctx, $envA, $ownerA, ['ref'], 15, 10)['state']
        === 'SUGGESTION_SYNTHETIC_ONLY');
    f218Check($edge->recheck($wireA, $envA, $ownerA, $ctx, 16));
    f218Check($counts->snapshot()['attempts'] === 1 && $audit->count() === 1);

    // A preflight-rejected field must not claim another job or preserve the
    // former accepted read; no additional counter or audit slot consumed.
    f218Check($queued->runNext($ctx, $envA, $ownerA,
        ['email'], 16, 10) === f218QueuedDenial());
    f218Check(!$edge->recheck($wireA, $envA, $ownerA, $ctx, 17));
    f218Check($counts->snapshot()['attempts'] === 1 && $audit->count() === 1);

    // New work with a fresh independent proof still succeeds.
    $queue->enqueue($ctx, 'SYNTHETIC_F218_RUN_B',
        'SYNTHETIC_F218_REQUEST_B', 17);
    [$envB, $ownerB] = f218Proof($ctx, 'B');
    $wireB = MockCrmReadRequestBuilder::fromContext(
        $ctx, 'SYNTHETIC_F218_REQUEST_B', ['ref']);
    f218Check($queued->runNext($ctx, $envB, $ownerB, ['ref'], 17, 10)['state']
        === 'SUGGESTION_SYNTHETIC_ONLY');
    f218Check($edge->recheck($wireB, $envB, $ownerB, $ctx, 18));
    f218Check($counts->snapshot()['attempts'] === 2 && $audit->count() === 2);

    // A batch whose second pair is invalid never starts a claim, but does
    // revoke the prior receipt at the batch admission boundary.
    $pairs = [
        ['envelope' => $envB, 'owner' => $ownerB],
        ['envelope' => 'SYNTHETIC_FORGED', 'owner' => null],
    ];
    f218Check($batch->drain($ctx, $pairs, ['ref'], 18, 10)
        === f218BatchDenial());
    f218Check(!$edge->recheck($wireB, $envB, $ownerB, $ctx, 19));
    f218Check($counts->snapshot()['attempts'] === 2 && $audit->count() === 2);

    // A new queued read may finish, then an EMPTY queue observation still
    // supersedes the old receipt; no hidden retry or external call occurs.
    $queue->enqueue($ctx, 'SYNTHETIC_F218_RUN_C',
        'SYNTHETIC_F218_REQUEST_C', 19);
    [$envC, $ownerC] = f218Proof($ctx, 'C');
    $wireC = MockCrmReadRequestBuilder::fromContext(
        $ctx, 'SYNTHETIC_F218_REQUEST_C', ['ref']);
    f218Check($queued->runNext($ctx, $envC, $ownerC, ['ref'], 19, 10)['state']
        === 'SUGGESTION_SYNTHETIC_ONLY');
    f218Check($edge->recheck($wireC, $envC, $ownerC, $ctx, 20));
    f218Check($queued->runNext(
        $ctx, $envC, $ownerC, ['ref'], 20, 10
    )['state'] === 'EMPTY');
    f218Check(!$edge->recheck($wireC, $envC, $ownerC, $ctx, 21));
    f218Check($counts->snapshot()['attempts'] === 3
        && $audit->count() === 3 && $effects->submitCount() === 0);
    f218Check($queue->counts()['CONCLUIDA'] === 3);

    echo 'F218_QUEUE_RECEIPT_FAIL_CLOSED_PASS checks=4 '
        . 'operational_decision=NO_GO real_authorization=false '
        . 'external_calls=0 write_calls=0' . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,
        "F218_QUEUE_RECEIPT_FAIL_CLOSED_DENIED operational_decision=NO_GO\n");
    exit(1);
}
