<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\Mocks\MockCrmReader;
use ImagineAI\MockBridge\{MockCrmReadBoundary, MockCrmReadRequest, MockCrmOwnerPolicy};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};

function f112Wire(?TenantContext $ctx = null): array {
    $ctx ??= context();
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_REQUEST_A',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $ctx->tenant,
        'principal_ref' => $ctx->principal,
        'resource_ref' => $ctx->resource,
        'purpose_ref' => $ctx->purpose,
        'fields' => ['ref'],
    ];
}
function f112Fixture(?TenantContext $ctx = null): array {
    $ctx ??= context();
    $env = new MockTrustEnvelope('SYNTHETIC_BRIDGE_ENV', 'SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', $ctx, 'SYNTHETIC_BRIDGE_NONCE', ['SYNTHETIC_LEAD_READ'], 10, 40);
    $owner = new MockOwnerScopeEvidence('SYNTHETIC_BRIDGE_OWNER', $env->digest,
        $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
    $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ']);
    $policy = new MockCrmOwnerPolicy();
    // Earlier fixture contexts may deliberately be invalid or foreign:
    // those contexts must not acquire a grant as a side effect of setup.
    try {
        $policy->grantFixture($ctx, ['ref', 'stage']);
    } catch (\ImagineAI\Domain\MockError) {
        // Leave deny-by-default for intentionally invalid fixtures.
    }
    return [new MockCrmReadBoundary($gateway, new MockCrmReader(), $policy),
        $env, $owner, $ctx];
}
return [
    'U12-01 strict request grants only local fictitious minimal lead projection' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $out = $bridge->evaluate(new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15);
        check($out['mode'] === 'SYNTHETIC_ONLY' && $out['state'] === 'READ_SYNTHETIC_ONLY');
        check($out['projection'] === ['ref' => 'SYNTHETIC_A_LEAD']);
        check($out['real_authorization'] === false && $out['external_calls'] === 0);
    },
    'U12-02 two explicitly permitted fields return only two fake attributes' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $wire = f112Wire($ctx); $wire['fields'] = ['ref', 'stage'];
        $out = $bridge->evaluate(new MockCrmReadRequest($wire), $env, $owner, $ctx, 15);
        check($out['projection'] === ['ref' => 'SYNTHETIC_A_LEAD', 'stage' => 'SYNTHETIC_NEW']);
    },
    'U12-03 extra fields or authority supplied in body fail closed' => function (): void {
        $wire = f112Wire(); $wire['role'] = 'SUPERUSER';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
        $wire = f112Wire(); $wire['authorization'] = 'SIGNED';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
    },
    'U12-04 unknown version and mutation operation denied' => function (): void {
        $w = f112Wire(); $w['contract'] = 'SYNTHETIC_CRM_AIA_READ_V2';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
        $w = f112Wire(); $w['operation'] = 'crm.task.create';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
    },
    'U12-05 field expansion to notes, contact or permissions denied' => function (): void {
        foreach (['email', 'phone', 'notes', 'permissions', 'text'] as $field) {
            $w = f112Wire(); $w['fields'] = [$field];
            denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
        }
    },
    'U12-06 duplicate, empty or nonlist field projections denied' => function (): void {
        foreach ([[], ['ref', 'ref'], ['ref', 'stage', 'email'], ['ref' => 'stage'], [['ref']]] as $fields) {
            $w = f112Wire(); $w['fields'] = $fields;
            denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
        }
    },
    'U12-07 tenant body mismatch does not select tenant' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $w = f112Wire(); $w['tenant_ref'] = 'SYNTHETIC_B';
        denied('BRIDGE_CONTEXT_DENIED', fn () => $bridge->evaluate(
            new MockCrmReadRequest($w), $env, $owner, $ctx, 15));
    },
    'U12-08 principal, resource and purpose context changes denied' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        foreach (['principal_ref', 'resource_ref', 'purpose_ref'] as $key) {
            $w = f112Wire(); $w[$key] = 'SYNTHETIC_OTHER';
            denied('BRIDGE_CONTEXT_DENIED', fn () => $bridge->evaluate(
                new MockCrmReadRequest($w), $env, $owner, $ctx, 15));
        }
    },
    'U12-09 real-looking identifiers or incorrect types denied' => function (): void {
        $w = f112Wire(); $w['tenant_ref'] = '12345';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
        $w = f112Wire(); $w['tenant_ref'] = 2;
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
    },
    'U12-10 missing owner, missing envelope and mismatched owner evidence denied' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $req = new MockCrmReadRequest(f112Wire());
        denied('TRUST_DENIED', fn () => $bridge->evaluate($req, null, $owner, $ctx, 15));
        denied('TRUST_DENIED', fn () => $bridge->evaluate($req, $env, null, $ctx, 15));
        $owner->revoke();
        denied('OWNER_DENIED', fn () => $bridge->evaluate($req, $env, $owner, $ctx, 15));
    },
    'U12-11 wrong issuer, audience and scope fail at trust gate' => function (): void {
        [$bridge, , , $ctx] = f112Fixture();
        foreach ([['SYNTHETIC_BAD_SERVICE', 'SYNTHETIC_CRM_API', 'SYNTHETIC_LEAD_READ'],
                  ['SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_BAD_API', 'SYNTHETIC_LEAD_READ'],
                  ['SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', 'SYNTHETIC_SUMMARY_DRAFT']] as $row) {
            [$issuer, $audience, $scope] = $row;
            $env = new MockTrustEnvelope('SYNTHETIC_BRIDGE_ENV', $issuer, $audience,
                $ctx, 'SYNTHETIC_NONCE', [$scope], 10, 40);
            $owner = new MockOwnerScopeEvidence('SYNTHETIC_OWNER', $env->digest,
                $ctx, $scope, 10, 40, true);
            denied('TRUST_DENIED', fn () => $bridge->evaluate(
                new MockCrmReadRequest(f112Wire()), $env, $owner, $ctx, 15));
        }
    },
    'U12-12 expired and revoked credentials denied even with valid request' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        denied('TRUST_DENIED', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire()), $env, $owner, $ctx, 40));
        $env->revoke();
        denied('TRUST_DENIED', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire()), $env, $owner, $ctx, 15));
    },
    'U12-13 nonce replay blocks repeated read on same gateway instance' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $req = new MockCrmReadRequest(f112Wire());
        $bridge->evaluate($req, $env, $owner, $ctx, 15);
        denied('TRUST_REPLAY_DENIED', fn () => $bridge->evaluate($req, $env, $owner, $ctx, 15));
    },
    'U12-14 missing key and unsupported field shape denied independent of object ordering' => function (): void {
        $w = f112Wire(); unset($w['purpose_ref']);
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
        $w = f112Wire(); $w['fields'] = 'ref';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($w));
        $reordered = array_reverse(f112Wire(), true);
        $request = new MockCrmReadRequest($reordered);
        check($request->tenantRef === 'SYNTHETIC_A');
    },
    'U12-15 request cannot widen beyond trusted resource even with valid proof' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $w = f112Wire(); $w['resource_ref'] = 'SYNTHETIC_B_LEAD';
        denied('BRIDGE_CONTEXT_DENIED', fn () => $bridge->evaluate(
            new MockCrmReadRequest($w), $env, $owner, $ctx, 15));
    },
    'U12-16 requested projection preserves field order without allowing extra fields' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $wire = f112Wire(); $wire['fields'] = ['stage', 'ref'];
        $out = $bridge->evaluate(new MockCrmReadRequest($wire), $env, $owner, $ctx, 15);
        check(array_keys($out['projection']) === ['stage', 'ref']);
        check($out['projection']['stage'] === 'SYNTHETIC_NEW');
    },
    'U12-17 no effect or approval fields in positive response' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $out = $bridge->evaluate(new MockCrmReadRequest(f112Wire()), $env, $owner, $ctx, 15);
        check(array_keys($out) === ['mode', 'contract', 'operation', 'request_ref', 'state', 'projection',
            'real_authorization', 'external_calls']);
        check(!array_key_exists('effect_ref', $out) && !array_key_exists('approved', $out));
    },
];
