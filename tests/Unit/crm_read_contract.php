<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\Mocks\MockCrmReader;
use ImagineAI\MockBridge\{MockCrmReadBoundary, MockCrmReadRequest, MockCrmOwnerPolicy, MockCrmReadRequestBuilder};
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

    'U59-01 oversized top-level wire key list is denied before copying or sorting' => function (): void {
        $wire = f112Wire();
        for ($i = 0; $i < 5000; ++$i) $wire['synthetic_extra_' . $i] = 'SYNTHETIC_NOISE';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
        check((new MockCrmReadRequest(f112Wire()))->ref === 'SYNTHETIC_REQUEST_A');
    },
    'U59-02 one-megabyte synthetic references are rejected in each reference position' => function (): void {
        $oversized = 'SYNTHETIC_' . str_repeat('A', 1024 * 1024);
        foreach (['request_ref', 'tenant_ref', 'principal_ref', 'resource_ref', 'purpose_ref'] as $field) {
            $wire = f112Wire();
            $wire[$field] = $oversized;
            denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
        }
    },
    'U59-03 exact 90-byte synthetic reference boundary remains accepted' => function (): void {
        $wire = f112Wire();
        $wire['request_ref'] = 'SYNTHETIC_' . str_repeat('A', 80);
        check(strlen($wire['request_ref']) === 90);
        $request = new MockCrmReadRequest($wire);
        check($request->ref === $wire['request_ref']);
        $wire['request_ref'] .= 'A';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
        $wire['request_ref'] = 'SYNTHETIC_';
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
    },
    'U59-04 oversized field list and contract strings cannot widen an OP-01 request' => function (): void {
        $wire = f112Wire();
        $wire['fields'] = array_fill(0, 5000, 'ref');
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
        $wire = f112Wire();
        $wire['contract'] = str_repeat('S', 1024 * 1024);
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
        $wire = f112Wire();
        $wire['operation'] = str_repeat('O', 1024 * 1024);
        denied('BRIDGE_SCHEMA_DENIED', fn () => new MockCrmReadRequest($wire));
        $reversed = array_reverse(f112Wire(), true);
        check((new MockCrmReadRequest($reversed))->ref === 'SYNTHETIC_REQUEST_A');
    },

    'U77-01 builder generates exact closed OP01 request from synthetic identity' => function (): void {
        $ctx = context();
        $wire = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_BUILDER_01', ['ref']);
        check(array_keys($wire) === [
            'contract', 'request_ref', 'operation', 'tenant_ref',
            'principal_ref', 'resource_ref', 'purpose_ref', 'fields']);
        check($wire['request_ref'] === 'SYNTHETIC_BUILDER_01');
        check($wire['tenant_ref'] === $ctx->tenant
            && $wire['principal_ref'] === $ctx->principal
            && $wire['resource_ref'] === $ctx->resource
            && $wire['purpose_ref'] === $ctx->purpose
            && $wire['operation'] === 'crm.lead.read_minimal');
        (new MockCrmReadRequest($wire))->assertMatches($ctx);
    },
    'U77-02 builder preserves two requested fields in explicit order' => function (): void {
        $ctx = context();
        $wire = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_BUILDER_02', ['stage', 'ref']);
        check($wire['fields'] === ['stage', 'ref']);
        [$boundary, $env, $owner] = f112Fixture();
        $out = $boundary->evaluate(new MockCrmReadRequest($wire),
            $env, $owner, $ctx, 15);
        check($out['projection'] === [
            'stage' => 'SYNTHETIC_NEW', 'ref' => $ctx->resource]);
    },
    'U77-03 tenant B can only be sourced from its trusted context' => function (): void {
        $ctxA = context();
        $ctxB = context('SYNTHETIC_B');
        $a = MockCrmReadRequestBuilder::fromContext(
            $ctxA, 'SYNTHETIC_BUILDER_03A', ['ref']);
        $b = MockCrmReadRequestBuilder::fromContext(
            $ctxB, 'SYNTHETIC_BUILDER_03B', ['ref']);
        check($a['tenant_ref'] === 'SYNTHETIC_A'
            && $b['tenant_ref'] === 'SYNTHETIC_B'
            && $b['resource_ref'] === 'SYNTHETIC_B_LEAD');
        denied('BRIDGE_CONTEXT_DENIED', fn () =>
            (new MockCrmReadRequest($b))->assertMatches($ctxA));
    },
    'U77-04 invalid and empty request references fail before output' => function (): void {
        $ctx = context();
        foreach (['BAD', 'SYNTHETIC_', 'SYNTHETIC_haslower',
                  'SYNTHETIC_A-B'] as $bad) {
            denied('BRIDGE_SCHEMA_DENIED', fn () =>
                MockCrmReadRequestBuilder::fromContext($ctx, $bad, ['ref']));
        }
    },
    'U77-05 large request reference and exact 90 byte boundary' => function (): void {
        $ctx = context();
        $full = 'SYNTHETIC_' . str_repeat('A', 80);
        check(strlen($full) === 90);
        check(MockCrmReadRequestBuilder::fromContext(
            $ctx, $full, ['ref'])['request_ref'] === $full);
        denied('BRIDGE_SCHEMA_DENIED', fn () =>
            MockCrmReadRequestBuilder::fromContext($ctx, $full . 'B', ['ref']));
        denied('BRIDGE_SCHEMA_DENIED', fn () =>
            MockCrmReadRequestBuilder::fromContext(
                $ctx, 'SYNTHETIC_' . str_repeat('C', 1024 * 1024), ['ref']));
    },
    'U77-06 empty or oversized field lists are denied without constructing' => function (): void {
        $ctx = context();
        foreach ([[], ['ref', 'stage', 'ref'],
                  array_fill(0, 20000, 'ref')] as $fields) {
            denied('BRIDGE_SCHEMA_DENIED', fn () =>
                MockCrmReadRequestBuilder::fromContext(
                    $ctx, 'SYNTHETIC_BUILDER_06', $fields));
        }
    },
    'U77-07 unknown private fields and duplicates never enter the output' => function (): void {
        $ctx = context();
        foreach ([['email'], ['notes'], ['ref', 'ref'], ['stage', 'stage']] as $fields) {
            denied('BRIDGE_SCHEMA_DENIED', fn () =>
                MockCrmReadRequestBuilder::fromContext(
                    $ctx, 'SYNTHETIC_BUILDER_07', $fields));
        }
    },
    'U77-08 associative and type-mixed fields are invalid' => function (): void {
        $ctx = context();
        foreach ([['field' => 'ref'], [0], ['ref', false], [null]] as $fields) {
            denied('BRIDGE_SCHEMA_DENIED', fn () =>
                MockCrmReadRequestBuilder::fromContext(
                    $ctx, 'SYNTHETIC_BUILDER_08', $fields));
        }
    },
    'U77-09 builder never copies arbitrary claims or bearer flags' => function (): void {
        $ctx = context();
        $wire = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_BUILDER_09', ['ref']);
        check(!isset($wire['authorization']) && !isset($wire['role'])
            && !isset($wire['is_admin']) && !isset($wire['allow'])
            && count($wire) === 8);
        $forged = $wire; $forged['tenant_ref'] = 'SYNTHETIC_B';
        denied('BRIDGE_CONTEXT_DENIED', fn () =>
            (new MockCrmReadRequest($forged))->assertMatches($ctx));
        (new MockCrmReadRequest($wire))->assertMatches($ctx);
    },
    'U77-10 idempotent builder has no nonce allocation nor side effects' => function (): void {
        $ctx = context();
        $first = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_BUILDER_10', ['ref']);
        $second = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_BUILDER_10', ['ref']);
        check($first === $second);
        $second['resource_ref'] = 'SYNTHETIC_B_LEAD';
        check($first['resource_ref'] === $ctx->resource);
    },
    'U77-11 a valid builder request does not bypass CRM owner denial' => function (): void {
        $ctx = context();
        [$facade, $policy, $audit, $env, $owner] =
            f117Fixture(new MockCrmOwnerPolicy());
        $wire = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_BUILDER_11', ['ref']);
        check($facade->handle($wire, $env, $owner, $ctx, 15)
            === f117GenericDenial());
        check($audit->count() === 1);
    },
    'U77-12 builder supports isolated read-only pilot with no effects' => function (): void {
        $ctx = context();
        [$pilot, $mut, $coordinator, $counts, $policy, $audit, $env, $owner] =
            f130Fixture(f130Grant());
        $wire = MockCrmReadRequestBuilder::fromContext(
            $ctx, 'SYNTHETIC_BUILDER_12', ['ref']);
        $result = $pilot->suggest($wire, $env, $owner, $ctx, 15,
            'SYNTHETIC_BUILDER_PILOT_12');
        check($result === f130Success() && $mut->submitCount() === 0
            && $mut->statusCount() === 0 && $audit->count() === 1
            && $counts->snapshot()['attempts'] === 1);
    },

];
