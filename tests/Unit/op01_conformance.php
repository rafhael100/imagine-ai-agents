<?php
declare(strict_types=1);

use ImagineAI\MockBridge\{MockCrmReadRequest, MockCrmOwnerPolicy, MockCrmReadPublicFacade};
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockTelemetry\{MockCrmReadEdgeBoundary, MockCrmReadEdgeCounters};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway, MockSharedTrustState};
use ImagineAI\Mocks\MockCrmReader;

/**
 * Consumer-side contract fixtures for a FAKE OP-01 request.
 * No CRM API, authorization, credentials, database or network.
 */
$fixturePath = dirname(__DIR__, 2) . '/contracts/fixtures/f115-crm-op01-conformance.synthetic.json';
$source = file_get_contents($fixturePath);
if ($source === false) throw new RuntimeException('F115 fixtures missing');
$suite = json_decode($source, true, 32, JSON_THROW_ON_ERROR);
if (!is_array($suite)
    || array_keys($suite) !== ['suite', 'contract', 'description', 'cases']
    || $suite['suite'] !== 'SYNTHETIC_F115_CONFORMANCE_V1'
    || $suite['contract'] !== 'SYNTHETIC_CRM_AIA_READ_V1'
    || !is_array($suite['cases']) || !array_is_list($suite['cases'])
    || count($suite['cases']) !== 12) {
    throw new RuntimeException('F115 fixtures incompatible');
}
$tests = [];
$denials = 0;
foreach ($suite['cases'] as $case) {
    if (!is_array($case) || !isset($case['id'], $case['title'], $case['patch'], $case['expect'])
        || !is_string($case['id']) || !preg_match('/^U15-(0[1-9]|1[0-2])$/D', $case['id'])
        || !is_string($case['title']) || !is_array($case['patch'])
        || !is_string($case['expect'])
        || !in_array($case['expect'], ['BRIDGE_SCHEMA_DENIED', 'BRIDGE_CONTEXT_DENIED',
            'READ_SYNTHETIC_ONLY'], true)) {
        throw new RuntimeException('F115 fixture invalid');
    }
    if ($case['expect'] !== 'READ_SYNTHETIC_ONLY') ++$denials;
    $name = $case['id'] . ' ' . $case['title'];
    if (isset($tests[$name])) throw new RuntimeException('F115 duplicate test');
    $tests[$name] = function () use ($case): void {
        [$boundary, $envelope, $owner, $context] = f112Fixture();
        $wire = f112Wire($context);
        foreach ($case['patch'] as $key => $value) {
            $wire[$key] = $value;
        }
        $call = static fn () => $boundary->evaluate(
            new MockCrmReadRequest($wire), $envelope, $owner, $context, 15);
        if ($case['expect'] !== 'READ_SYNTHETIC_ONLY') {
            denied($case['expect'], $call);
            return;
        }
        if (!isset($case['expected_fields']) || !is_array($case['expected_fields'])) {
            throw new RuntimeException('F115 expected projection missing');
        }
        $out = $call();
        check($out['state'] === 'READ_SYNTHETIC_ONLY'
            && $out['real_authorization'] === false && $out['external_calls'] === 0);
        check(array_keys($out['projection']) === $case['expected_fields']);
        foreach ($out['projection'] as $value) {
            check(is_string($value) && str_starts_with($value, 'SYNTHETIC_'));
        }
    };
}
if ($denials !== 10 || count($tests) !== 12) {
    throw new RuntimeException('F115 unexpected coverage');
}

/**
 * F163: no HTTP, real CRM, token, database or external call.
 * Synthetic-only rehearsal; distinct from bilateral acceptance and its open CSV.
 */
function f163Fixture(
    ?MockCrmOwnerPolicy $policy = null,
    ?MockSharedTrustState $state = null,
    ?MockCrmReadEdgeCounters $counters = null,
    ?MockCrmReadAuditTrail $audit = null,
): array {
    $ctx = context();
    $policy ??= new MockCrmOwnerPolicy();
    $audit ??= new MockCrmReadAuditTrail();
    $counters ??= new MockCrmReadEdgeCounters();
    $env = new MockTrustEnvelope('SYNTHETIC_F163_ENVELOPE',
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', $ctx,
        'SYNTHETIC_F163_NONCE', ['SYNTHETIC_LEAD_READ'], 10, 40);
    $owner = new MockOwnerScopeEvidence('SYNTHETIC_F163_OWNER',
        $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
    $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ'], $state);
    $facade = new MockCrmReadPublicFacade($gateway, new MockCrmReader(),
        $policy, $audit);
    $edge = new MockCrmReadEdgeBoundary($facade, $counters);
    return [$edge, $policy, $audit, $counters, $env, $owner, $ctx];
}

function f163Denial(): array {
    return ['contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'DENIED_OR_NOT_FOUND', 'projection' => null,
        'real_authorization' => false, 'external_calls' => 0];
}

/*
 * U63-OP01-N01..N11 and P01..P02 exercise synthetic analogues of all 13
 * CSV rows. These do NOT change estado_execucao=NAO_EXECUTADO, prove IAM,
 * simulate an actual restart/distributed cache, or authorize a CRM pilot.
 */
$tests += [
    'U63-OP01-N01 synthetic missing/revoked service proof denies' => function (): void {
        $store = new MockSharedTrustState();
        [$edge, $policy, $audit, $counts, $env, $owner, $ctx] =
            f163Fixture(null, $store);
        $policy->grantFixture($ctx, ['ref']);
        check($edge->handle(f112Wire($ctx), null, $owner, $ctx, 15) === f163Denial());
        $env->revoke();
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        check($store->countConsumed() === 0 && $audit->count() === 2);
        check($counts->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 2);
    },
    'U63-OP01-N02 synthetic mismatched service issuer/audience denies' => function (): void {
        $store = new MockSharedTrustState();
        [$edge, $policy, $audit, , , , $ctx] = f163Fixture(null, $store);
        $policy->grantFixture($ctx, ['ref']);
        foreach ([['SYNTHETIC_FOREIGN_SERVICE', 'SYNTHETIC_CRM_API'],
                  ['SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_FOREIGN_AUDIENCE']] as $pair) {
            $env = new MockTrustEnvelope('SYNTHETIC_F163_WRONG_ENV', $pair[0],
                $pair[1], $ctx, 'SYNTHETIC_F163_BAD_NONCE',
                ['SYNTHETIC_LEAD_READ'], 10, 40);
            $owner = new MockOwnerScopeEvidence('SYNTHETIC_F163_BAD_OWNER',
                $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
            check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        }
        check($store->countConsumed() === 0 && $audit->count() === 2);
    },
    'U63-OP01-N03 synthetic suspended tenant/member cannot read' => function (): void {
        $store = new MockSharedTrustState();
        [$edge, $policy, $audit, , $env, $owner, $ctx] = f163Fixture(null, $store);
        $policy->grantFixture($ctx, ['ref']);
        $policy->setTenantSuspended($ctx->tenant, true);
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        $policy->setTenantSuspended($ctx->tenant, false);
        $policy->setMemberSuspended($ctx->tenant, $ctx->principal, true);
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        check($store->countConsumed() === 0 && $audit->count() === 2);
        $policy->setMemberSuspended($ctx->tenant, $ctx->principal, false);
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
    },
    'U63-OP01-N04 synthetic foreign versus missing resource denies uniformly' => function (): void {
        $store = new MockSharedTrustState();
        [$edge, $policy, $audit, , $env, $owner, $ctx] = f163Fixture(null, $store);
        $policy->grantFixture($ctx, ['ref']);
        $out = [];
        foreach (['SYNTHETIC_B_LEAD', 'SYNTHETIC_MISSING_LEAD'] as $resource) {
            $wire = f112Wire($ctx);
            $wire['resource_ref'] = $resource;
            $out[] = $edge->handle($wire, $env, $owner, $ctx, 15);
        }
        check($out === [f163Denial(), f163Denial()]);
        check($store->countConsumed() === 0 && $audit->count() === 2);
    },
    'U63-OP01-N05 synthetic personal/unknown projection fields denied' => function (): void {
        $store = new MockSharedTrustState();
        [$edge, $policy, $audit, $counts, $env, $owner, $ctx] =
            f163Fixture(null, $store);
        $policy->grantFixture($ctx, ['ref', 'stage']);
        foreach (['email', 'notes'] as $forbidden) {
            $wire = f112Wire($ctx);
            $wire['fields'] = [$forbidden];
            check($edge->handle($wire, $env, $owner, $ctx, 15) === f163Denial());
        }
        check($audit->count() === 0 && $store->countConsumed() === 0);
        check($counts->snapshot()['outcomes']['SCHEMA_DENIED'] === 2);
    },
    'U63-OP01-N06 synthetic write operation and unknown contract denied' => function (): void {
        $store = new MockSharedTrustState();
        [$edge, $policy, $audit, , $env, $owner, $ctx] = f163Fixture(null, $store);
        $policy->grantFixture($ctx, ['ref']);
        foreach ([['operation' => 'crm.task.create'],
                  ['contract' => 'SYNTHETIC_CRM_AIA_READ_V2']] as $patch) {
            check($edge->handle(array_replace(f112Wire($ctx), $patch),
                $env, $owner, $ctx, 15) === f163Denial());
        }
        check($store->countConsumed() === 0 && $audit->count() === 0);
    },
    'U63-OP01-N07 synthetic two gateway instances share replay memory' => function (): void {
        // One process-local object across two gateway instances, NOT distributed
        // persistence or a tested real restart.
        $state = new MockSharedTrustState();
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$first, , $auditA, , $env, $owner, $ctx] = f163Fixture($policy, $state);
        [$second, , $auditB] = f163Fixture($policy, $state);
        check($first->handle(f112Wire($ctx), $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($second->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        check($state->countConsumed() === 1
            && $auditA->count() === 1 && $auditB->count() === 1);
    },
    'U63-OP01-N08 synthetic owner revocation before delayed read denies' => function (): void {
        // Delayed local invocation: there is no queue or delivery worker.
        $state = new MockSharedTrustState();
        [$edge, $policy, $audit, , $env, $owner, $ctx] = f163Fixture(null, $state);
        $policy->grantFixture($ctx, ['ref']);
        $policy->revokeFixture($ctx);
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 18) === f163Denial());
        check($state->countConsumed() === 0 && $audit->count() === 1);
    },
    'U63-OP01-N09 synthetic owner and nonce store outages deny uniformly' => function (): void {
        $state = new MockSharedTrustState();
        [$edge, $policy, $audit, , $env, $owner, $ctx] = f163Fixture(null, $state);
        $policy->grantFixture($ctx, ['ref']);
        $policy->setAvailable(false);
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        $policy->setAvailable(true);
        $state->setAvailable(false);
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        check($state->countConsumed() === 0 && $audit->count() === 2);
    },
    'U63-OP01-N10 synthetic secret-bearing body excluded from audit/telemetry' => function (): void {
        $state = new MockSharedTrustState();
        [$edge, $policy, $audit, $counts, $env, $owner, $ctx] =
            f163Fixture(null, $state);
        $policy->grantFixture($ctx, ['ref']);
        $wire = f112Wire($ctx);
        $wire['authorization'] = 'Bearer SYNTHETIC_SUPER_SECRET_MARKER_123';
        $result = $edge->handle($wire, $env, $owner, $ctx, 15);
        check($result === f163Denial());
        $visible = json_encode([$result, $audit->entries(), $counts->snapshot()],
            JSON_THROW_ON_ERROR);
        foreach (['Bearer', 'SUPER_SECRET', 'authorization', 'SYNTHETIC_A_LEAD'] as $secret) {
            check(!str_contains($visible, $secret));
        }
        check($state->countConsumed() === 0 && $audit->count() === 0);
    },
    'U63-OP01-N11 synthetic exhausted telemetry stops parsing and nonce use' => function (): void {
        $state = new MockSharedTrustState();
        $counts = new MockCrmReadEdgeCounters(1);
        [$edge, $policy, $audit, , $env, $owner, $ctx] =
            f163Fixture(null, $state, $counts);
        $policy->grantFixture($ctx, ['ref']);
        $bad = f112Wire($ctx);
        $bad['role'] = 'SYNTHETIC_ADMIN';
        check($edge->handle($bad, $env, $owner, $ctx, 15) === f163Denial());
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f163Denial());
        check($counts->snapshot()['attempts'] === 1
            && $state->countConsumed() === 0 && $audit->count() === 0);
    },
    'U63-OP01-P01 synthetic own-resource minimal read returns one field' => function (): void {
        [$edge, $policy, $audit, $counts, $env, $owner, $ctx] = f163Fixture();
        $policy->grantFixture($ctx, ['ref']);
        $result = $edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        check($result === [
            'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
            'state' => 'READ_SYNTHETIC_ONLY',
            'projection' => ['ref' => 'SYNTHETIC_A_LEAD'],
            'real_authorization' => false, 'external_calls' => 0,
        ]);
        check($audit->count() === 1
            && $counts->snapshot()['outcomes']['READ_SYNTHETIC_ONLY'] === 1);
    },
    'U63-OP01-P02 synthetic ordered two-field projection remains minimal' => function (): void {
        [$edge, $policy, $audit, $counts, $env, $owner, $ctx] = f163Fixture();
        $policy->grantFixture($ctx, ['ref', 'stage']);
        $wire = f112Wire($ctx);
        $wire['fields'] = ['stage', 'ref'];
        $result = $edge->handle($wire, $env, $owner, $ctx, 15);
        check($result['state'] === 'READ_SYNTHETIC_ONLY'
            && $result['projection'] === [
                'stage' => 'SYNTHETIC_NEW', 'ref' => 'SYNTHETIC_A_LEAD',
            ] && $result['real_authorization'] === false
            && $result['external_calls'] === 0);
        check($audit->count() === 1
            && $counts->snapshot()['outcomes']['READ_SYNTHETIC_ONLY'] === 1);
    },
];

return $tests;
