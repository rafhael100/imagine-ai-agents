<?php
declare(strict_types=1);

/**
 * F211: Actual Agents in-memory trust/owner lifecycle checked by the pinned
 * CRM Core response inspector. No HTTP, real identity, storage or client data.
 */
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\MockCrmReadPublicFacade;
use ImagineAI\MockBridge\MockCrmOwnerPolicy;
use ImagineAI\MockTrust\MockTrustEnvelope;
use ImagineAI\MockTrust\MockOwnerScopeEvidence;
use ImagineAI\MockTrust\MockTrustGateway;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\Mocks\MockCrmReader;
use App\Core\Leads\AgentsReadResponseInspector;
use App\Core\Leads\AgentsReadResponseAssessment;
use App\Support\Synthetic\AiaReadOnlyMockBoundary;

$root = dirname(__DIR__);
$crm = $root . '/_synthetic_crm_fixture';
foreach ([
    '/app/Core/Leads/AgentsReadResponseAssessment.php',
    '/app/Core/Leads/AgentsReadResponseInspector.php',
    '/app/Support/Synthetic/AiaReadOnlyMockBoundary.php',
] as $path) {
    if (!is_file($crm . $path)) {
        fwrite(STDERR, "F211_CRM_SOURCE_MISSING NO_GO\n");
        exit(2);
    }
    require_once $crm . $path;
}
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root . '/src/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function f211Assert(bool $ok): void {
    if (!$ok) throw new RuntimeException('F211_FAIL_CLOSED_DENIED');
}
function f211Fixture(int $number, bool $grant = true): array {
    $context = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'
    );
    $envelope = new MockTrustEnvelope(
        'SYNTHETIC_F211_ENVELOPE_' . $number,
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', $context,
        'SYNTHETIC_F211_NONCE_' . $number,
        ['SYNTHETIC_LEAD_READ'], 10, 40
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_F211_OWNER_' . $number,
        $envelope->digest, $context, 'SYNTHETIC_LEAD_READ', 10, 40, true
    );
    $policy = new MockCrmOwnerPolicy();
    if ($grant) $policy->grantFixture($context, ['ref', 'stage']);
    $facade = new MockCrmReadPublicFacade(
        new MockTrustGateway('SYNTHETIC_AGENT_SERVICE',
            'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ']),
        new MockCrmReader(), $policy, new MockCrmReadAuditTrail()
    );
    $wire = [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F211_REQUEST_' . $number,
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $context->tenant,
        'principal_ref' => $context->principal,
        'resource_ref' => $context->resource,
        'purpose_ref' => $context->purpose,
        'fields' => ['ref', 'stage'],
    ];
    return [$facade, $policy, $envelope, $owner, $context, $wire];
}
function f211Inspect(array $response, bool $success,
                     AgentsReadResponseInspector $inspector): void {
    $expected = [
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'projection' => null,
        'real_authorization' => false,
        'external_calls' => 0,
    ];
    f211Assert(array_keys($response) === array_keys($expected));
    f211Assert($response['real_authorization'] === false
        && $response['external_calls'] === 0);
    if ($success) {
        f211Assert($response['state'] === 'READ_SYNTHETIC_ONLY'
            && $response['projection'] === [
                'ref' => 'SYNTHETIC_A_LEAD', 'stage' => 'SYNTHETIC_NEW'
            ]);
    } else {
        f211Assert($response === $expected);
    }
    $assessment = $inspector->inspect($response, ['ref', 'stage']);
    f211Assert($assessment->reasonCode === (
        $success ? 'SYNTHETIC_PROJECTION_UNTRUSTED' : 'SYNTHETIC_DENIAL_ONLY'
    ));
    f211Assert($assessment->isSyntheticFormatConsistent());
    f211Assert(!$assessment->canReadRealLead()
        && !$assessment->canUseCustomerData()
        && !$assessment->canGrantAgentAccess()
        && !$assessment->canWriteLead()
        && !$assessment->canExportLead()
        && !$assessment->canEnableRoute());
    f211Assert(array_keys((array) $assessment) === ['reasonCode']);
    f211Assert(AiaReadOnlyMockBoundary::inspect($response) === $expected);
}
try {
    $inspector = new AgentsReadResponseInspector();
    // Local fault injections, not test executions of real CRM IAM or API.
    $modes = [
        'baseline', 'replay', 'no_grant', 'tenant_suspended',
        'member_suspended', 'owner_unavailable', 'policy_revoked',
        'envelope_revoked', 'owner_proof_revoked', 'expired',
        'missing_envelope', 'wrong_audience', 'foreign_tenant',
        'unknown_field',
    ];
    $reads = 0;
    $denials = 0;
    foreach ($modes as $i => $mode) {
        [$facade, $policy, $envelope, $owner, $ctx, $wire] =
            f211Fixture($i + 1, $mode !== 'no_grant');
        $now = 15;
        switch ($mode) {
            case 'replay':
                f211Inspect($facade->handle($wire, $envelope, $owner, $ctx, $now),
                            true, $inspector);
                break;
            case 'tenant_suspended':
                $policy->setTenantSuspended('SYNTHETIC_A', true);
                break;
            case 'member_suspended':
                $policy->setMemberSuspended('SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL', true);
                break;
            case 'owner_unavailable':
                $policy->setAvailable(false);
                break;
            case 'policy_revoked':
                $policy->revokeFixture($ctx);
                break;
            case 'envelope_revoked':
                $envelope->revoke();
                break;
            case 'owner_proof_revoked':
                $owner->revoke();
                break;
            case 'expired':
                $now = 40;
                break;
            case 'missing_envelope':
                $envelope = null;
                break;
            case 'wrong_audience':
                $envelope = new MockTrustEnvelope(
                    'SYNTHETIC_F211_WRONG_AUDIENCE',
                    'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_OTHER_API',
                    $ctx, 'SYNTHETIC_F211_WRONG_NONCE',
                    ['SYNTHETIC_LEAD_READ'], 10, 40
                );
                $owner = new MockOwnerScopeEvidence(
                    'SYNTHETIC_F211_WRONG_OWNER',
                    $envelope->digest, $ctx, 'SYNTHETIC_LEAD_READ',
                    10, 40, true
                );
                break;
            case 'foreign_tenant':
                $wire['tenant_ref'] = 'SYNTHETIC_B';
                break;
            case 'unknown_field':
                $wire['fields'] = ['email'];
                break;
        }
        $out = $facade->handle($wire, $envelope, $owner, $ctx, $now);
        $ok = $mode === 'baseline';
        f211Inspect($out, $ok, $inspector);
        if ($ok) ++$reads; else ++$denials;
    }
    f211Assert(count($modes) === 14 && $reads === 1 && $denials === 13);

    // A previously observed fictional success cannot be revived by a later
    // revoke/regrant, owner proof revocation or outage/recovery.
    $transitions = [
        'policy_aba', 'owner_proof_revoke', 'envelope_revoke',
        'tenant_suspend', 'owner_outage_recovery',
    ];
    foreach ($transitions as $i => $transition) {
        [$facade, $policy, $envelope, $owner, $ctx, $wire] =
            f211Fixture(100 + $i);
        $out = $facade->handle($wire, $envelope, $owner, $ctx, 15);
        f211Inspect($out, true, $inspector);
        f211Assert($facade->recheck($wire, $envelope, $owner, $ctx, 16));
        switch ($transition) {
            case 'policy_aba':
                $policy->revokeFixture($ctx);
                $policy->grantFixture($ctx, ['ref', 'stage']);
                break;
            case 'owner_proof_revoke':
                $owner->revoke();
                break;
            case 'envelope_revoke':
                $envelope->revoke();
                break;
            case 'tenant_suspend':
                $policy->setTenantSuspended('SYNTHETIC_A', true);
                break;
            case 'owner_outage_recovery':
                $policy->setAvailable(false);
                $policy->setAvailable(true);
                break;
        }
        f211Assert(!$facade->recheck($wire, $envelope, $owner, $ctx, 17));
        f211Assert(!$facade->recheck($wire, $envelope, $owner, $ctx, 18));
    }
    echo 'F211_OWNER_TRUST_FAIL_CLOSED_PASS cases=14 denials=13 ' .
        'receipt_revocations=5 operational_decision=NO_GO ' .
        'real_authorization=false external_calls=0' . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "F211_OWNER_TRUST_FAIL_CLOSED_DENIED operational_decision=NO_GO\n");
    exit(1);
}
