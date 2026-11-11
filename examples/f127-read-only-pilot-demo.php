<?php
declare(strict_types=1);

/**
 * Demonstration CLI for the offline F1-26 synthetic read-only pilot.
 * It accepts no arguments or external input; no HTTP, CRM, secrets or real LLM.
 * Intended for product review before any real integration is approved.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmOwnerPolicy, MockCrmReadBoundary, MockCrmReadPublicFacade, MockCrmReadRequestBuilder};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockTelemetry\{MockCrmReadEdgeBoundary, MockCrmReadEdgeCounters};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockPilot\MockReadOnlyPilot;
use ImagineAI\Mocks\{MockCrmReader, MockSupervisedGateway, MockAiSuggestionProvider};

function demoFixture(bool $grant): array
{
    $context = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD',
    );
    $envelope = new MockTrustEnvelope(
        'SYNTHETIC_DEMO_ENVELOPE', 'SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', $context, 'SYNTHETIC_DEMO_NONCE',
        ['SYNTHETIC_LEAD_READ'], 10, 40,
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_DEMO_OWNER', $envelope->digest,
        $context, 'SYNTHETIC_LEAD_READ', 10, 40, true,
    );
    $ownerPolicy = new MockCrmOwnerPolicy();
    if ($grant) $ownerPolicy->grantFixture($context, ['ref', 'stage']);
    $audit = new MockCrmReadAuditTrail();
    $counters = new MockCrmReadEdgeCounters();
    $trust = new MockTrustGateway(
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ'],
    );
    $facade = new MockCrmReadPublicFacade(
        $trust, new MockCrmReader(), $ownerPolicy, $audit,
    );
    $edge = new MockCrmReadEdgeBoundary($facade, $counters);
    $mutations = new MockSupervisedGateway();
    $coordinator = new SupervisedCoordinator(
        $context, $mutations, new MockAiSuggestionProvider(),
    );
    $pilot = new MockReadOnlyPilot($context, $edge, $coordinator);
    // The consumer derives all identity/resource fields from the bound
    // synthetic context, never from a proposed request body or AI output.
    $wire = MockCrmReadRequestBuilder::fromContext(
        $context, 'SYNTHETIC_DEMO_REQUEST', ['ref', 'stage']);
    return [$pilot, $wire, $envelope, $owner, $context, $audit, $counters,
        $coordinator, $mutations];
}

try {
    [$pilot, $wire, $envelope, $owner, $context, $audit, $counters,
        $coordinator, $mutations] = demoFixture(true);
    $allowed = $pilot->suggest($wire, $envelope, $owner, $context,
        15, 'SYNTHETIC_DEMO_RUN_ALLOWED');
    $replay = $pilot->suggest($wire, $envelope, $owner, $context,
        15, 'SYNTHETIC_DEMO_RUN_REPLAY');
    $foreignWire = $wire;
    $foreignWire['tenant_ref'] = 'SYNTHETIC_B';
    $foreign = $pilot->suggest($foreignWire, $envelope, $owner, $context,
        15, 'SYNTHETIC_DEMO_RUN_FOREIGN');
    $invalidFieldWire = $wire;
    $invalidFieldWire['fields'] = ['email'];
    $invalidField = $pilot->suggest($invalidFieldWire, $envelope, $owner, $context,
        15, 'SYNTHETIC_DEMO_RUN_FIELD');

    [$deniedPilot, $deniedWire, $deniedEnvelope, $deniedOwner, $deniedContext,
        $deniedAudit, $deniedCounters, $deniedCoordinator, $deniedMutations] =
        demoFixture(false);
    $ungranted = $deniedPilot->suggest(
        $deniedWire, $deniedEnvelope, $deniedOwner, $deniedContext,
        15, 'SYNTHETIC_DEMO_RUN_UNGRANTED',
    );

    $expectedDenial = 'DENIED_OR_NOT_FOUND';
    if ($allowed['state'] !== 'SUGGESTION_SYNTHETIC_ONLY'
        || $allowed['summary'] !== 'SYNTHETIC summary proposal only'
        || $replay['state'] !== $expectedDenial
        || $foreign['state'] !== $expectedDenial
        || $invalidField['state'] !== $expectedDenial
        || $ungranted['state'] !== $expectedDenial
        || count($coordinator->events()) !== 4
        || $deniedCoordinator->events() !== []
        || $mutations->submitCount() !== 0
        || $mutations->statusCount() !== 0
        || $deniedMutations->submitCount() !== 0
        || $deniedMutations->statusCount() !== 0
        || $audit->count() !== 3
        || $deniedAudit->count() !== 1
        || $counters->snapshot()['attempts'] !== 4
        || $deniedCounters->snapshot()['attempts'] !== 1) {
        throw new RuntimeException('DEMO_INVARIANT_FAILED');
    }

    // Public output uses stable categories, not hashes, tenant refs, raw
    // request envelopes, prompts, tokens, or mutable gateway effects.
    echo json_encode([
        'demo' => 'SYNTHETIC_F127_READ_ONLY_DEMO_V1',
        'mode' => 'OFFLINE_SYNTHETIC_ONLY',
        'operational_decision' => 'NO_GO',
        'scenarios' => [
            'authorized_minimal_read' => $allowed['state'],
            'nonce_replay' => $replay['state'],
            'cross_tenant_request' => $foreign['state'],
            'private_field_request' => $invalidField['state'],
            'missing_owner_grant' => $ungranted['state'],
        ],
        'summary' => $allowed['summary'],
        'write_calls' => 0,
        'external_calls' => 0,
        'real_authorization' => false,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable) {
    fwrite(STDERR, "SYNTHETIC_DEMO_FAILED\n");
    exit(1);
}
