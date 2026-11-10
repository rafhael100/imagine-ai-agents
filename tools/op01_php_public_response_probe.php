<?php
declare(strict_types=1);

/**
 * EVID-10: offline output witness from the actual synthetic PHP facade.
 * Never accepts inputs, reads environment, invokes HTTP, or authenticates CRM.
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
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\Mocks\MockCrmReader;

/** Build separate fictional requests; no shared CRM, token or network. */
function evid10Fixture(int $ordinal, array $fields, bool $grant): array
{
    $context = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD',
    );
    $envelope = new MockTrustEnvelope(
        'SYNTHETIC_EVID10_ENVELOPE_' . $ordinal,
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        $context, 'SYNTHETIC_EVID10_NONCE_' . $ordinal,
        ['SYNTHETIC_LEAD_READ'], 10, 40,
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_EVID10_OWNER_' . $ordinal, $envelope->digest,
        $context, 'SYNTHETIC_LEAD_READ', 10, 40, true,
    );
    $policy = new MockCrmOwnerPolicy();
    if ($grant) $policy->grantFixture($context, ['ref', 'stage']);
    $gateway = new MockTrustGateway(
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ'],
    );
    $facade = new MockCrmReadPublicFacade(
        $gateway, new MockCrmReader(), $policy, new MockCrmReadAuditTrail(),
    );
    $wire = [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_EVID10_REQUEST_' . $ordinal,
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $context->tenant,
        'principal_ref' => $context->principal,
        'resource_ref' => $context->resource,
        'purpose_ref' => $context->purpose,
        'fields' => $fields,
    ];
    return [$facade, $wire, $envelope, $owner, $context];
}

try {
    $cases = [];

    [$facade, $wire, $envelope, $owner, $context] =
        evid10Fixture(1, ['ref'], true);
    $cases[] = ['case' => 'single_ref', 'fields' => ['ref'],
        'response' => $facade->handle($wire, $envelope, $owner, $context, 15)];
    $cases[] = ['case' => 'replay', 'fields' => ['ref'],
        'response' => $facade->handle($wire, $envelope, $owner, $context, 15)];

    [$facade, $wire, $envelope, $owner, $context] =
        evid10Fixture(2, ['stage', 'ref'], true);
    $cases[] = ['case' => 'ordered_two_fields', 'fields' => ['stage', 'ref'],
        'response' => $facade->handle($wire, $envelope, $owner, $context, 15)];

    [$facade, $wire, $envelope, $owner, $context] =
        evid10Fixture(3, ['stage'], false);
    $cases[] = ['case' => 'ungranted', 'fields' => ['stage'],
        'response' => $facade->handle($wire, $envelope, $owner, $context, 15)];

    [$facade, $wire, $envelope, $owner, $context] =
        evid10Fixture(4, ['ref'], true);
    $wire['tenant_ref'] = 'SYNTHETIC_B';
    $cases[] = ['case' => 'cross_tenant', 'fields' => ['ref'],
        'response' => $facade->handle($wire, $envelope, $owner, $context, 15)];

    [$facade, $wire, $envelope, $owner, $context] =
        evid10Fixture(5, ['ref'], true);
    $wire['fields'] = ['email'];
    $cases[] = ['case' => 'unknown_field', 'fields' => ['email'],
        'response' => $facade->handle($wire, $envelope, $owner, $context, 15)];

    echo json_encode([
        'probe' => 'SYNTHETIC_OP01_PUBLIC_RESPONSE_PROBE_V1',
        'real_authorization' => false,
        'external_calls' => 0,
        'cases' => $cases,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable) {
    fwrite(STDERR, "SYNTHETIC_PROBE_FAILED\n");
    exit(1);
}
