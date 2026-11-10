<?php
declare(strict_types=1);

use ImagineAI\MockPilot\MockReadOnlyPilot;
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\MockCrmOwnerPolicy;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockTelemetry\MockCrmReadEdgeCounters;
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};
use ImagineAI\Mocks\{MockSupervisedGateway, MockAiSuggestionProvider};

function f130Fixture(
    ?MockCrmOwnerPolicy $policy = null,
    ?MockCrmReadAuditTrail $audit = null,
    ?MockCrmReadEdgeCounters $counters = null,
): array {
    [$edge, $counters, $policy, $audit, $env, $owner, $ctx] =
        f118Fixture($policy, $audit, $counters);
    $mutationGateway = new MockSupervisedGateway();
    $coordinator = new SupervisedCoordinator(
        $ctx, $mutationGateway, new MockAiSuggestionProvider()
    );
    $pilot = new MockReadOnlyPilot($ctx, $edge, $coordinator);
    return [$pilot, $mutationGateway, $coordinator, $counters,
        $policy, $audit, $env, $owner, $ctx];
}

function f130Grant(): MockCrmOwnerPolicy
{
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture(context(), ['ref', 'stage']);
    return $policy;
}

function f130Proof(TenantContext $ctx, string $suffix): array
{
    $env = new MockTrustEnvelope(
        'SYNTHETIC_F130_ENV_' . $suffix,
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        $ctx, 'SYNTHETIC_F130_NONCE_' . $suffix,
        ['SYNTHETIC_LEAD_READ'], 10, 40,
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_F130_OWNER_' . $suffix,
        $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true,
    );
    return [$env, $owner];
}

function f130Denial(): array
{
    return [
        'contract' => 'SYNTHETIC_PILOT_READ_ONLY_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'summary' => null,
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ];
}

function f130Success(): array
{
    return [
        'contract' => 'SYNTHETIC_PILOT_READ_ONLY_V1',
        'state' => 'SUGGESTION_SYNTHETIC_ONLY',
        'summary' => 'SYNTHETIC summary proposal only',
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ];
}

return [
    'U30-01 minimal read can create a fictional draft without CRM mutation' => function (): void {
        [$pilot, $mut, $c, $counters, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        $result = $pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_01');
        check($result === f130Success());
        check($mut->submitCount() === 0 && $mut->statusCount() === 0);
        check(array_column($c->events(), 'code') === [
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED',
            'SYNTHETIC_SUGGESTED', 'SYNTHETIC_RUN_CHANGED',
        ]);
        check($counters->snapshot()['outcomes']['READ_SYNTHETIC_ONLY'] === 1
            && $audit->count() === 1);
    },
    'U30-02 ordered two-field allowed read creates no extra public data' => function (): void {
        [$pilot, $mut, $c, , , , $env, $owner, $ctx] = f130Fixture(f130Grant());
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        check($pilot->suggest($wire, $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_02') === f130Success());
        check($mut->submitCount() === 0 && count($c->events()) === 4);
    },
    'U30-03 absent CRM-owned grant denies before any agent run' => function (): void {
        [$pilot, $mut, $c, $counts, , $audit, $env, $owner, $ctx] = f130Fixture();
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_03') === f130Denial());
        check($c->events() === [] && $mut->submitCount() === 0);
        check($counts->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1
            && $audit->count() === 1);
    },
    'U30-04 missing envelope does not create a synthetic suggestion' => function (): void {
        [$pilot, $mut, $c, , , , , $owner, $ctx] = f130Fixture(f130Grant());
        check($pilot->suggest(f112Wire($ctx), null, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_04') === f130Denial());
        check($c->events() === [] && $mut->submitCount() === 0);
    },
    'U30-05 missing owner evidence is not implicitly trusted' => function (): void {
        [$pilot, $mut, $c, , , , $env, , $ctx] = f130Fixture(f130Grant());
        check($pilot->suggest(f112Wire($ctx), $env, null, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_05') === f130Denial());
        check($c->events() === [] && $mut->submitCount() === 0);
    },
    'U30-06 nonce replay is denied without creating another run' => function (): void {
        [$pilot, $mut, $c, $counts, , , $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_06A') === f130Success());
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_06B') === f130Denial());
        check(count($c->events()) === 4 && $mut->submitCount() === 0);
        check($counts->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1);
    },
    'U30-07 body tenant substitution is denied before provider use' => function (): void {
        [$pilot, , $c, , , , $env, $owner, $ctx] = f130Fixture(f130Grant());
        $wire = f112Wire($ctx); $wire['tenant_ref'] = 'SYNTHETIC_B';
        check($pilot->suggest($wire, $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_07') === f130Denial());
        check($c->events() === []);
    },
    'U30-08 runtime context mismatch is denied before parsing or read' => function (): void {
        [$pilot, , $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        check($pilot->suggest(f112Wire($ctx), $env, $owner, context('SYNTHETIC_B'),
            15, 'SYNTHETIC_PILOT_RUN_08') === f130Denial());
        check($c->events() === [] && $counts->snapshot()['attempts'] === 0
            && $audit->count() === 0);
    },
    'U30-09 forbidden field cannot enter a draft or inner audit' => function (): void {
        [$pilot, , $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        $wire = f112Wire($ctx); $wire['fields'] = ['email'];
        check($pilot->suggest($wire, $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_09') === f130Denial());
        check($c->events() === []
            && $counts->snapshot()['outcomes']['SCHEMA_DENIED'] === 1
            && $audit->count() === 0);
    },
    'U30-10 invalid run ID denies without burning a request nonce' => function (): void {
        [$pilot, , $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'RUN_UNSAFE') === f130Denial());
        check($c->events() === [] && $counts->snapshot()['attempts'] === 0
            && $audit->count() === 0);
    },
    'U30-11 audit exhaustion refuses a fresh request before agent work' => function (): void {
        [$pilot, $mut, $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant(), new MockCrmReadAuditTrail(1));
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_11A') === f130Success());
        [$fresh, $freshOwner] = f130Proof($ctx, 'AUDIT_B');
        check($pilot->suggest(f112Wire($ctx), $fresh, $freshOwner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_11B') === f130Denial());
        check(count($c->events()) === 4 && $audit->count() === 1
            && $mut->submitCount() === 0);
        check($counts->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1);
    },
    'U30-12 two distinct authorized reads produce two isolated draft runs' => function (): void {
        [$pilot, $mut, $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_12A') === f130Success());
        [$fresh, $freshOwner] = f130Proof($ctx, 'SUCCESS_B');
        check($pilot->suggest(f112Wire($ctx), $fresh, $freshOwner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_12B') === f130Success());
        check(count($c->events()) === 8 && $mut->submitCount() === 0
            && $mut->statusCount() === 0);
        check($counts->snapshot()['outcomes']['READ_SYNTHETIC_ONLY'] === 2
            && $audit->count() === 2);
    },
];
