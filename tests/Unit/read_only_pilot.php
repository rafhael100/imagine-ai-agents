<?php
declare(strict_types=1);

use ImagineAI\MockPilot\{MockReadOnlyPilot, MockQueuedReadOnlyPilot};
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\MockCrmOwnerPolicy;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockTelemetry\MockCrmReadEdgeCounters;
use ImagineAI\MockOrchestration\{SupervisedCoordinator, MockReadOnlyRunQueue};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};
use ImagineAI\Mocks\{MockSupervisedGateway, MockAiSuggestionProvider};
use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\Run;

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

/** A deliberately untrusted, purely synthetic provider for failure-path tests. */
final class F129FaultProvider implements AiSuggestionProvider
{
    public int $calls = 0;

    public function __construct(private readonly string $mode) {}

    public function suggest(Run $run, array $fields): array
    {
        ++$this->calls;
        if ($this->mode === 'THROW' || ($this->mode === 'THROW_ONCE' && $this->calls === 1)) {
            throw new \RuntimeException('SYNTHETIC_PRIVATE_PROVIDER_FAILURE');
        }
        if ($this->mode === 'FORGE') {
            return [
                'kind' => 'SYNTHETIC_SUGGESTION',
                'version' => 'mock-v1',
                'run' => $run->id,
                'source' => $run->context->resource,
                'text' => 'SYNTHETIC_PRIVATE_UNTRUSTED_OUTPUT',
            ];
        }
        return (new MockAiSuggestionProvider())->suggest($run, $fields);
    }
}

function f129Fixture(F129FaultProvider $provider, bool $allowed = true): array
{
    [$edge, $counts, , $audit, $env, $owner, $ctx] =
        f118Fixture($allowed ? f130Grant() : null);
    $gateway = new MockSupervisedGateway();
    $coordinator = new SupervisedCoordinator($ctx, $gateway, $provider);
    return [new MockReadOnlyPilot($ctx, $edge, $coordinator),
        $coordinator, $gateway, $counts, $audit, $env, $owner, $ctx];
}



/** Provider that changes the fictional owner's permission during a draft. */
final class F167MutatingSuggestionProvider implements AiSuggestionProvider
{
    public int $calls = 0;

    public function __construct(private readonly \Closure $onSuggest) {}

    public function suggest(Run $run, array $fields): array
    {
        ++$this->calls;
        ($this->onSuggest)();
        return (new MockAiSuggestionProvider())->suggest($run, $fields);
    }
}

/** Return [pilot, coordinator, mutations, edge counters, audit, proofs, ctx, policy, provider, state]. */
function f167Fixture(\Closure $onSuggest, bool $useShared = false): array
{
    $ctx = context();
    $policy = f130Grant();
    [$env, $owner] = f130Proof($ctx, 'F167');
    $shared = $useShared ? new \ImagineAI\MockTrust\MockSharedTrustState() : null;
    $trust = new \ImagineAI\MockTrust\MockTrustGateway(
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ'], $shared);
    $audit = new MockCrmReadAuditTrail();
    $counts = new MockCrmReadEdgeCounters();
    $facade = new \ImagineAI\MockBridge\MockCrmReadPublicFacade(
        $trust, new \ImagineAI\Mocks\MockCrmReader(), $policy, $audit);
    $edge = new \ImagineAI\MockTelemetry\MockCrmReadEdgeBoundary($facade, $counts);
    $mutations = new MockSupervisedGateway();
    $provider = new F167MutatingSuggestionProvider(function () use (
        $onSuggest, $policy, $env, $owner, $ctx, $shared
    ): void {
        $onSuggest($policy, $env, $owner, $ctx, $shared);
    });
    $coordinator = new SupervisedCoordinator($ctx, $mutations, $provider);
    return [new MockReadOnlyPilot($ctx, $edge, $coordinator),
        $coordinator, $mutations, $counts, $audit, $env, $owner,
        $ctx, $policy, $provider, $shared, $edge];
}

/** Offline queued pilot: independent queue, same fictional OP-01 edge. */
function f179QueuedFixture(bool $allowed = true): array
{
    [$pilot, $mut, $coord, $counts, $policy, $audit, $env, $owner, $ctx] =
        f130Fixture($allowed ? f130Grant() : new MockCrmOwnerPolicy());
    $queue = new MockReadOnlyRunQueue($ctx);
    return [new MockQueuedReadOnlyPilot($ctx, $queue, $pilot),
        $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx];
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

    'U31-01 duplicate pilot run rejects fresh read before burning nonce or audit' => function (): void {
        [$pilot, $mut, $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        $id = 'SYNTHETIC_PILOT_RUN_COLLISION';
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx, 15, $id) === f130Success());
        [$newEnv, $newOwner] = f130Proof($ctx, 'COLLISION_FRESH');
        check($pilot->suggest(f112Wire($ctx), $newEnv, $newOwner, $ctx, 15, $id) === f130Denial());
        check(count($c->events()) === 4 && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $mut->submitCount() === 0);
        // The fresh envelope was not consumed: the same proof may open a new run.
        check($pilot->suggest(f112Wire($ctx), $newEnv, $newOwner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_AFTER_COLLISION') === f130Success());
        check($counts->snapshot()['attempts'] === 2 && $audit->count() === 2);
    },
    'U31-02 misbound coordinator context denies before CRM-owned edge' => function (): void {
        [$edge, $counts, , $audit, $env, $owner, $ctx] = f118Fixture(f130Grant());
        $coordinator = new SupervisedCoordinator(context('SYNTHETIC_B'),
            new MockSupervisedGateway(), new MockAiSuggestionProvider());
        $pilot = new MockReadOnlyPilot($ctx, $edge, $coordinator);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_MISBOUND') === f130Denial());
        check($counts->snapshot()['attempts'] === 0
            && $audit->count() === 0 && $coordinator->events() === []);
    },
    'U31-03 insufficient trace room refuses before consuming a synthetic read' => function (): void {
        [$pilot, $mut, $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        for ($i = 0; $i < 1021; ++$i) {
            $c->start('SYNTHETIC_PREFILL_RUN_' . $i);
        }
        check(count($c->events()) === 1021);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_NO_ROOM') === f130Denial());
        check(count($c->events()) === 1021 && $counts->snapshot()['attempts'] === 0
            && $audit->count() === 0 && $mut->submitCount() === 0);
    },
    'U31-04 exact four-slot room admits one run, then rejects further reads' => function (): void {
        [$pilot, $mut, $c, $counts, , $audit, $env, $owner, $ctx] =
            f130Fixture(f130Grant());
        for ($i = 0; $i < 1020; ++$i) {
            $c->start('SYNTHETIC_BOUNDARY_RUN_' . $i);
        }
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_LAST_FOUR') === f130Success());
        check(count($c->events()) === 1024);
        [$secondEnv, $secondOwner] = f130Proof($ctx, 'AFTER_FULL');
        check($pilot->suggest(f112Wire($ctx), $secondEnv, $secondOwner, $ctx,
            15, 'SYNTHETIC_PILOT_RUN_AFTER_FULL') === f130Denial());
        check(count($c->events()) === 1024 && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $mut->submitCount() === 0);
    },

    'U32-01 provider exception closes active synthetic run as failed' => function (): void {
        $provider = new F129FaultProvider('THROW');
        [$pilot, $c, $gateway, $counts, $audit, $env, $owner, $ctx] = f129Fixture($provider);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_PROVIDER_THROW') === f130Denial());
        check(array_column($c->events(), 'code') === [
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED', 'SYNTHETIC_RUN_CHANGED',
        ]);
        check($c->events()[2]['run_state'] === 'SYNTHETIC_FALHOU');
        check($provider->calls === 1 && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $gateway->submitCount() === 0);
    },
    'U32-02 forged provider output cannot leave an active run or escape boundary' => function (): void {
        $provider = new F129FaultProvider('FORGE');
        [$pilot, $c, $gateway, $counts, $audit, $env, $owner, $ctx] = f129Fixture($provider);
        $out = $pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_PROVIDER_FORGE');
        check($out === f130Denial());
        check(array_column($c->events(), 'code') === [
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED',
            'SYNTHETIC_SUGGESTED', 'SYNTHETIC_RUN_CHANGED',
        ]);
        check($c->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check(!str_contains(json_encode($out, JSON_THROW_ON_ERROR),
            'SYNTHETIC_PRIVATE_UNTRUSTED_OUTPUT'));
        check(!str_contains(json_encode($c->events(), JSON_THROW_ON_ERROR),
            'SYNTHETIC_PRIVATE_UNTRUSTED_OUTPUT'));
        check($gateway->submitCount() === 0 && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1);
    },
    'U32-03 denied owner policy never invokes a faulting provider or begins run' => function (): void {
        $provider = new F129FaultProvider('THROW');
        [$pilot, $c, $gateway, $counts, $audit, $env, $owner, $ctx] =
            f129Fixture($provider, false);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_PROVIDER_UNAUTHORIZED') === f130Denial());
        check($provider->calls === 0 && $c->events() === []
            && $counts->snapshot()['attempts'] === 1 && $audit->count() === 1
            && $gateway->submitCount() === 0);
    },
    'U32-04 a later fresh authorized run recovers after failed provider invocation' => function (): void {
        $provider = new F129FaultProvider('THROW_ONCE');
        [$pilot, $c, $gateway, $counts, $audit, $env, $owner, $ctx] = f129Fixture($provider);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_PILOT_PROVIDER_FAIL_FIRST') === f130Denial());
        [$freshEnv, $freshOwner] = f130Proof($ctx, 'PROVIDER_RECOVERY');
        check($pilot->suggest(f112Wire($ctx), $freshEnv, $freshOwner, $ctx,
            15, 'SYNTHETIC_PILOT_PROVIDER_RECOVER') === f130Success());
        check(array_column($c->events(), 'code') === [
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED', 'SYNTHETIC_RUN_CHANGED',
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED',
            'SYNTHETIC_SUGGESTED', 'SYNTHETIC_RUN_CHANGED',
        ]);
        check($c->events()[2]['run_state'] === 'SYNTHETIC_FALHOU'
            && $c->events()[6]['run_state'] === 'SYNTHETIC_CONCLUIDA');
        check($provider->calls === 2 && $gateway->submitCount() === 0
            && $counts->snapshot()['attempts'] === 2 && $audit->count() === 2);
    },

    'U67-01 revoked owner grant during suggestion fails final delivery' => function (): void {
        [$pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx, , $provider] =
            f167Fixture(function ($policy, $env, $owner, $ctx): void {
                $policy->revokeFixture($ctx);
            });
        $result = $pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_OWNER_REVOKED');
        check($result === f130Denial() && $provider->calls === 1);
        check(array_column($coord->events(), 'code') === [
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED',
            'SYNTHETIC_SUGGESTED', 'SYNTHETIC_RUN_CHANGED']);
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check($mut->submitCount() === 0 && $audit->count() === 1
            && $counts->snapshot()['attempts'] === 1);
    },
    'U67-02 suspended tenant during suggestion returns no summary' => function (): void {
        [$pilot, $coord, , , , $env, $owner, $ctx] = f167Fixture(
            function ($policy, $env, $owner, $ctx): void {
                $policy->setTenantSuspended($ctx->tenant, true);
            });
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_TENANT') === f130Denial());
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
    },
    'U67-03 member suspension during suggestion fails closed' => function (): void {
        [$pilot, $coord, , , , $env, $owner, $ctx] = f167Fixture(
            function ($policy, $env, $owner, $ctx): void {
                $policy->setMemberSuspended($ctx->tenant, $ctx->principal, true);
            });
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_MEMBER') === f130Denial());
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
    },
    'U67-04 owner policy outage while preparing text denies final output' => function (): void {
        [$pilot, $coord, , , , $env, $owner, $ctx] = f167Fixture(
            function ($policy): void { $policy->setAvailable(false); });
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_OWNER_OUTAGE') === f130Denial());
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
    },
    'U67-05 trust envelope revoked by provider suppresses synthetic draft' => function (): void {
        [$pilot, $coord, , , , $env, $owner, $ctx] = f167Fixture(
            function ($policy, $envelope): void { $envelope->revoke(); });
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_ENVELOPE') === f130Denial());
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
    },
    'U67-06 revoked owner scope proof after read suppresses draft' => function (): void {
        [$pilot, $coord, , , , $env, $owner, $ctx] = f167Fixture(
            function ($policy, $envelope, $owner): void { $owner->revoke(); });
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_SCOPE_REVOKED') === f130Denial());
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
    },
    'U67-07 shared digest revocation during suggestion is denied without retry' => function (): void {
        [$pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx, , , $state] =
            f167Fixture(function ($policy, $envelope, $owner, $ctx, $state): void {
                $state->revokeDigest($envelope->digest);
            }, true);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_DIGEST_REVOKED') === f130Denial());
        check($state->countConsumed() === 1 && $state->countRevoked() === 1);
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check($mut->submitCount() === 0 && $audit->count() === 1
            && $counts->snapshot()['attempts'] === 1);
    },
    'U67-08 shared trust store unavailable after read denies final output' => function (): void {
        [$pilot, $coord, , , , $env, $owner, $ctx, , , $state] =
            f167Fixture(function ($policy, $envelope, $owner, $ctx, $state): void {
                $state->setAvailable(false);
            }, true);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_TRUST_OUTAGE') === f130Denial());
        check($state->countConsumed() === 1
            && $coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
    },
    'U67-09 approved unchanged owner passes without duplicate nonce or edge audit' => function (): void {
        [$pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx, , $provider, $state, $edge] =
            f167Fixture(function (): void {}, true);
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        check($pilot->suggest($wire, $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_VALID') === f130Success());
        check($provider->calls === 1 && $state->countConsumed() === 1
            && $counts->snapshot()['attempts'] === 1 && $audit->count() === 1);
        check(array_column($coord->events(), 'code') === [
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED',
            'SYNTHETIC_SUGGESTED', 'SYNTHETIC_RUN_CHANGED']);
        check($coord->events()[3]['run_state'] === 'SYNTHETIC_CONCLUIDA');
        check($edge->recheck($wire, $env, $owner, $ctx, 15) === true);
        check($state->countConsumed() === 1 && $audit->count() === 1);
        check($mut->submitCount() === 0 && $mut->statusCount() === 0);
    },
    'U67-10 failed draft authorization does not prevent fresh authorized run' => function (): void {
        $calls = 0;
        [$pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx, $policy, $provider] =
            f167Fixture(function ($policy, $envelope, $owner, $ctx) use (&$calls): void {
                ++$calls;
                if ($calls === 1) $policy->revokeFixture($ctx);
            });
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC45_FIRST') === f130Denial());
        $policy->grantFixture($ctx, ['ref', 'stage']);
        [$freshEnv, $freshOwner] = f130Proof($ctx, 'F167_RECOVERY');
        check($pilot->suggest(f112Wire($ctx), $freshEnv, $freshOwner, $ctx,
            15, 'SYNTHETIC_SEC45_RECOVERY') === f130Success());
        check($calls === 2 && $provider->calls === 2
            && $counts->snapshot()['attempts'] === 2 && $audit->count() === 2);
        check(array_column($coord->events(), 'run_state')[3] === 'SYNTHETIC_FALHOU');
        check($coord->events()[7]['run_state'] === 'SYNTHETIC_CONCLUIDA');
        check($mut->submitCount() === 0);
    },

    'U68-10 grant revoked and immediately restored by provider cannot finish draft' => function (): void {
        [$pilot, $coordinator, $mut, $counts, $audit, $env, $owner, $ctx, $policy, $provider] =
            f167Fixture(function ($policy, $env, $owner, $ctx): void {
                $policy->revokeFixture($ctx);
                $policy->grantFixture($ctx, ['ref', 'stage']);
            });
        $before = $policy->revision();
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC46_GRANT_ABA') === f130Denial());
        check($policy->revision() === $before + 2);
        check($coordinator->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check($provider->calls === 1 && $mut->submitCount() === 0
            && $audit->count() === 1 && $counts->snapshot()['attempts'] === 1);
    },
    'U68-11 member suspended and resumed during provider is still denied' => function (): void {
        [$pilot, $coordinator, $mut, $counts, $audit, $env, $owner, $ctx, $policy] =
            f167Fixture(function ($policy, $env, $owner, $ctx): void {
                $policy->setMemberSuspended($ctx->tenant, $ctx->principal, true);
                $policy->setMemberSuspended($ctx->tenant, $ctx->principal, false);
            });
        $before = $policy->revision();
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC46_MEMBER_ABA') === f130Denial());
        check($policy->revision() === $before + 2);
        check($coordinator->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check($mut->submitCount() === 0 && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1);
    },
    'U68-12 policy outage and recovery during provider invalidates former read' => function (): void {
        [$pilot, $coordinator, $mut, $counts, $audit, $env, $owner, $ctx, $policy] =
            f167Fixture(function ($policy): void {
                $policy->setAvailable(false);
                $policy->setAvailable(true);
            });
        $before = $policy->revision();
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC46_OUTAGE_ABA') === f130Denial());
        check($policy->revision() === $before + 2);
        check($coordinator->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check($mut->submitCount() === 0 && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1);
    },

    'U71-07 shared trust outage and recovery during provider fails the draft' => function (): void {
        [$pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx, ,
            $provider, $state] = f167Fixture(
                function ($policy, $envelope, $owner, $ctx, $state): void {
                    $state->setAvailable(false);
                    $state->setAvailable(true);
                }, true);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC49_TRUST_ABA') === f130Denial());
        check($state->revision() === 2 && $state->countConsumed() === 1);
        check($provider->calls === 1
            && $coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check($counts->snapshot()['attempts'] === 1 && $audit->count() === 1
            && $mut->submitCount() === 0);
    },
    'U71-08 fresh unrelated trust revocation during provider denies the draft' => function (): void {
        [$pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx, ,
            $provider, $state] = f167Fixture(
                function ($policy, $envelope, $owner, $ctx, $state): void {
                    $state->revokeDigest(str_repeat('c', 64));
                }, true);
        check($pilot->suggest(f112Wire($ctx), $env, $owner, $ctx,
            15, 'SYNTHETIC_SEC49_REVISION') === f130Denial());
        check($state->revision() === 1 && $state->countRevoked() === 1
            && $state->countConsumed() === 1);
        check($provider->calls === 1
            && $coord->events()[3]['run_state'] === 'SYNTHETIC_FALHOU');
        check($mut->submitCount() === 0 && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1);
    },

    'U78-01 empty synthetic queue has no claim and all counters zero' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        check($q->claim(context(), 10, 5) === null && $q->events() === []);
        check(array_sum($q->counts()) === 0);
    },
    'U78-02 enqueue is idempotent without duplicate audit slots' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $one = $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_02',
            'SYNTHETIC_Q78_REQUEST_02', 10);
        $two = $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_02',
            'SYNTHETIC_Q78_REQUEST_02', 10);
        check($one === $two && $one['state'] === 'PENDENTE'
            && count($q->events()) === 1 && $q->counts()['PENDENTE'] === 1);
    },
    'U78-03 changed request on existing job rejects before state mutation' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_03',
            'SYNTHETIC_Q78_REQUEST_A', 10);
        denied('QUEUE_IDEMPOTENCY_CONFLICT', fn () => $q->enqueue(
            context(), 'SYNTHETIC_Q78_RUN_03', 'SYNTHETIC_Q78_REQUEST_B', 11));
        check($q->counts()['PENDENTE'] === 1 && count($q->events()) === 1);
    },
    'U78-04 claims are FIFO and never grant a CRM read by themselves' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_A', 'SYNTHETIC_Q78_REQ_A', 10);
        $q->enqueue(context(), 'SYNTHETIC_Q78_B', 'SYNTHETIC_Q78_REQ_B', 10);
        $a = $q->claim(context(), 11, 5);
        $b = $q->claim(context(), 11, 5);
        check($a['run_ref'] === 'SYNTHETIC_Q78_A'
            && $b['run_ref'] === 'SYNTHETIC_Q78_B'
            && $a['state'] === 'RESERVADA'
            && $b['state'] === 'RESERVADA'
            && !isset($a['token']) && !isset($a['authorization']));
        check($q->claim(context(), 12, 5) === null);
    },
    'U78-05 queue is bound to one tenant and rejects foreign claims' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_A', 'SYNTHETIC_Q78_REQ_A', 10);
        denied('CONTEXT_DENIED', fn () => $q->claim(context('SYNTHETIC_B'), 11, 5));
        denied('CONTEXT_DENIED', fn () => $q->cancel(
            context('SYNTHETIC_B'), 'SYNTHETIC_Q78_A', 11));
        check($q->counts()['PENDENTE'] === 1 && count($q->events()) === 1);
    },
    'U78-06 invalid references and oversized values are refused' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        foreach (['BAD', 'SYNTHETIC_', 'SYNTHETIC_a', 'SYNTHETIC_'
            . str_repeat('Z', 1024 * 1024)] as $bad) {
            denied('QUEUE_REF_DENIED', fn () => $q->enqueue(
                context(), $bad, 'SYNTHETIC_Q78_VALID', 10));
        }
        denied('QUEUE_REF_DENIED', fn () => $q->enqueue(
            context(), 'SYNTHETIC_Q78_RUN_06', 'SYNTHETIC_BAD-REQUEST', 10));
        check($q->counts()['PENDENTE'] === 0 && $q->events() === []);
    },
    'U78-07 finishing a valid claim is stable under duplicate confirmation' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_07', 'SYNTHETIC_Q78_REQ_07', 10);
        $claim = $q->claim(context(), 12, 5);
        $done = $q->finish(context(), $claim['run_ref'],
            $claim['claim_ref'], 'CONCLUIDA', 13);
        $again = $q->finish(context(), $claim['run_ref'],
            $claim['claim_ref'], 'CONCLUIDA', 14);
        check($done === $again && $done['state'] === 'CONCLUIDA'
            && $done['attempts'] === 1 && count($q->events()) === 3);
        check($q->claim(context(), 15, 5) === null);
    },
    'U78-08 failed local claims do not retry or create new jobs' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_08', 'SYNTHETIC_Q78_REQ_08', 10);
        $claim = $q->claim(context(), 11, 5);
        $out = $q->finish(context(), $claim['run_ref'],
            $claim['claim_ref'], 'FALHOU', 12);
        check($out['state'] === 'FALHOU'
            && $q->claim(context(), 13, 5) === null
            && $q->counts()['FALHOU'] === 1);
    },
    'U78-09 wrong claim marker and illegal outcome deny without trace' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_09', 'SYNTHETIC_Q78_REQ_09', 10);
        $claim = $q->claim(context(), 11, 5);
        denied('QUEUE_CLAIM_DENIED', fn () => $q->finish(
            context(), $claim['run_ref'], 'SYNTHETIC_WRONG', 'CONCLUIDA', 12));
        denied('QUEUE_STATE_DENIED', fn () => $q->finish(
            context(), $claim['run_ref'], $claim['claim_ref'], 'ALLOW', 12));
        check($q->counts()['RESERVADA'] === 1 && count($q->events()) === 2);
    },
    'U78-10 expired claim becomes indeterminate and never requeued' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_10', 'SYNTHETIC_Q78_REQ_10', 10);
        $claim = $q->claim(context(), 11, 3);
        denied('QUEUE_CLAIM_EXPIRED', fn () => $q->finish(
            context(), $claim['run_ref'], $claim['claim_ref'], 'CONCLUIDA', 14));
        check($q->counts()['INDETERMINADO'] === 1
            && $q->claim(context(), 15, 3) === null
            && array_column($q->events(), 'code') === [
                'SYNTHETIC_ENQUEUED', 'SYNTHETIC_CLAIMED',
                'SYNTHETIC_LEASE_EXPIRED']);
    },
    'U78-11 claim expiry audit reserves space and does not retry silently' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_11A', 'SYNTHETIC_Q78_REQ_11A', 10);
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_11B', 'SYNTHETIC_Q78_REQ_11B', 10);
        $claim = $q->claim(context(), 11, 2);
        $next = $q->claim(context(), 13, 2);
        check($next['run_ref'] === 'SYNTHETIC_Q78_RUN_11B'
            && $next['claim_ref'] !== $claim['claim_ref']
            && $q->counts()['INDETERMINADO'] === 1
            && $q->counts()['RESERVADA'] === 1);
    },
    'U78-12 only pending jobs are cancellable, idempotently' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_12A', 'SYNTHETIC_Q78_REQ_12A', 10);
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_12B', 'SYNTHETIC_Q78_REQ_12B', 10);
        $one = $q->cancel(context(), 'SYNTHETIC_Q78_RUN_12A', 10);
        check($one === $q->cancel(context(), 'SYNTHETIC_Q78_RUN_12A', 11));
        $next = $q->claim(context(), 12, 5);
        denied('QUEUE_STATE_DENIED', fn () => $q->cancel(
            context(), 'SYNTHETIC_Q78_RUN_12B', 13));
        check($next['run_ref'] === 'SYNTHETIC_Q78_RUN_12B'
            && $q->counts()['CANCELADA'] === 1);
    },
    'U78-13 clock rollback and invalid leases fail closed' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_RUN_13', 'SYNTHETIC_Q78_REQ_13', 15);
        denied('QUEUE_TIME_DENIED', fn () => $q->claim(context(), 14, 2));
        foreach ([0, 301] as $ticks) {
            denied('QUEUE_LEASE_DENIED', fn () => $q->claim(context(), 16, $ticks));
        }
        denied('QUEUE_LEASE_DENIED', fn () => $q->claim(context(), PHP_INT_MAX, 5));
        check($q->counts()['PENDENTE'] === 1);
    },
    'U78-14 finite queue rejects a 65th job without eviction' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        for ($i = 0; $i < 64; ++$i) {
            $q->enqueue(context(), 'SYNTHETIC_Q78_CAP_' . $i,
                'SYNTHETIC_Q78_REQ_' . $i, 10);
        }
        denied('QUEUE_CAPACITY_DENIED', fn () => $q->enqueue(
            context(), 'SYNTHETIC_Q78_CAP_64', 'SYNTHETIC_Q78_REQ_64', 10));
        check($q->counts()['PENDENTE'] === 64 && count($q->events()) === 64);
        check($q->status(context(), 'SYNTHETIC_Q78_CAP_0')['state'] === 'PENDENTE');
    },
    'U78-15 queue events hash identifiers and expose no payloads' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_Q78_PRIVATE_RUN',
            'SYNTHETIC_Q78_PRIVATE_REQUEST', 10);
        $claim = $q->claim(context(), 11, 5);
        $q->finish(context(), $claim['run_ref'],
            $claim['claim_ref'], 'CONCLUIDA', 12);
        $visible = json_encode([$q->events(), $q->counts()], JSON_THROW_ON_ERROR);
        check(!str_contains($visible, 'SYNTHETIC_A_LEAD')
            && !str_contains($visible, 'SYNTHETIC_Q78_PRIVATE_RUN')
            && !str_contains($visible, 'SYNTHETIC_Q78_PRIVATE_REQUEST')
            && !str_contains($visible, 'SYNTHETIC_PRINCIPAL')
            && count($q->events()) === 3);
    },


    'U79-01 queue to OP01 to suggestion returns only fixed fictional draft' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_01', 'SYNTHETIC_Q79_REQ_01', 10);
        $out = $runner->runNext($ctx, $env, $owner, ['ref'], 15, 5);
        check($out === ['contract' => 'SYNTHETIC_QUEUED_PILOT_V1',
            'state' => 'SUGGESTION_SYNTHETIC_ONLY',
            'summary' => 'SYNTHETIC summary proposal only',
            'real_authorization' => false, 'external_calls' => 0,
            'write_calls' => 0]);
        check($queue->counts()['CONCLUIDA'] === 1
            && $mut->submitCount() === 0 && $mut->statusCount() === 0
            && $counts->snapshot()['attempts'] === 1 && $audit->count() === 1);
        check(count($coord->events()) === 4);
    },
    'U79-02 empty queue does not read CRM or create Run' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $out = $runner->runNext($ctx, $env, $owner, ['ref'], 15, 5);
        check($out['state'] === 'EMPTY' && $out['summary'] === null
            && $coord->events() === [] && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0 && $mut->submitCount() === 0);
    },
    'U79-03 invalid field denies without claiming or consuming nonce' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_03', 'SYNTHETIC_Q79_REQ_03', 10);
        $out = $runner->runNext($ctx, $env, $owner, ['email'], 15, 5);
        check($out['state'] === 'DENIED_OR_NOT_FOUND'
            && $queue->counts()['PENDENTE'] === 1
            && $counts->snapshot()['attempts'] === 0
            && $audit->count() === 0 && $coord->events() === []);
    },
    'U79-04 duplicate queued Run cannot re-read after success' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_04', 'SYNTHETIC_Q79_REQ_04', 10);
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_04', 'SYNTHETIC_Q79_REQ_04', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($runner->runNext($ctx, $env, $owner, ['ref'], 16, 5)['state']
            === 'EMPTY');
        check($counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $queue->counts()['CONCLUIDA'] === 1);
    },
    'U79-05 missing owner grant fails locally without a started Run' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture(false);
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_05', 'SYNTHETIC_Q79_REQ_05', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)['state']
            === 'DENIED_OR_NOT_FOUND');
        check($queue->counts()['FALHOU'] === 1
            && $coord->events() === [] && $mut->submitCount() === 0);
    },
    'U79-06 missing trust evidence denies and settles queue as failed' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_06', 'SYNTHETIC_Q79_REQ_06', 10);
        check($runner->runNext($ctx, null, null, ['ref'], 15, 5)['state']
            === 'DENIED_OR_NOT_FOUND');
        check($queue->counts()['FALHOU'] === 1
            && $coord->events() === [] && $mut->submitCount() === 0);
    },
    'U79-07 foreign tenant cannot trigger or burn a queued read' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_07', 'SYNTHETIC_Q79_REQ_07', 10);
        check($runner->runNext(context('SYNTHETIC_B'), $env, $owner,
            ['ref'], 15, 5)['state'] === 'DENIED_OR_NOT_FOUND');
        check($queue->counts()['PENDENTE'] === 1
            && $counts->snapshot()['attempts'] === 0 && $coord->events() === []);
    },
    'U79-08 reused nonce cannot authorize a second pending job' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_08A', 'SYNTHETIC_Q79_REQ_08A', 10);
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_08B', 'SYNTHETIC_Q79_REQ_08B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($runner->runNext($ctx, $env, $owner, ['ref'], 16, 5)['state']
            === 'DENIED_OR_NOT_FOUND');
        check($queue->counts()['CONCLUIDA'] === 1
            && $queue->counts()['FALHOU'] === 1
            && $mut->submitCount() === 0 && count($coord->events()) === 4);
    },
    'U79-09 expired reservation never triggers automatic retry' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_09', 'SYNTHETIC_Q79_REQ_09', 10);
        $queue->claim($ctx, 11, 2);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 13, 5)['state']
            === 'EMPTY');
        check($queue->counts()['INDETERMINADO'] === 1
            && $coord->events() === [] && $counts->snapshot()['attempts'] === 0);
    },
    'U79-10 cancellation before reservation prevents all read attempts' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_10', 'SYNTHETIC_Q79_REQ_10', 10);
        $queue->cancel($ctx, 'SYNTHETIC_Q79_RUN_10', 11);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 12, 5)['state']
            === 'EMPTY');
        check($coord->events() === [] && $queue->counts()['CANCELADA'] === 1
            && $mut->submitCount() === 0 && $audit->count() === 0);
    },
    'U79-11 distinct valid synthetic proofs can complete two FIFO jobs' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_11A', 'SYNTHETIC_Q79_REQ_11A', 10);
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_RUN_11B', 'SYNTHETIC_Q79_REQ_11B', 10);
        [$envB, $ownerB] = f130Proof($ctx, 'Q79_11B');
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($runner->runNext($ctx, $envB, $ownerB, ['ref'], 16, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($queue->counts()['CONCLUIDA'] === 2
            && $audit->count() === 2 && $mut->submitCount() === 0
            && count($coord->events()) === 8);
    },
    'U79-12 queued output exposes no tenant, resource, nonce or claim marker' => function (): void {
        [$runner, $queue, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_Q79_PRIVATE_RUN',
            'SYNTHETIC_Q79_PRIVATE_REQ', 10);
        $out = $runner->runNext($ctx, $env, $owner, ['ref'], 15, 5);
        $visible = json_encode($out, JSON_THROW_ON_ERROR);
        check(!str_contains($visible, $ctx->resource)
            && !str_contains($visible, $ctx->principal)
            && !str_contains($visible, $ctx->tenant)
            && !str_contains($visible, 'SYNTHETIC_Q79_PRIVATE')
            && !str_contains($visible, 'claim_ref')
            && $out['write_calls'] === 0 && $out['external_calls'] === 0);
    },

];
