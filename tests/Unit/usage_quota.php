<?php
declare(strict_types=1);

use ImagineAI\Domain\{Attempt, MockError, TenantContext};
use ImagineAI\MockUsage\{MockUsageLedger, MockUsagePolicy, MockUsageProof};
use ImagineAI\Mocks\MockCrmEffectGateway;

/** Only synthetic fixtures. This suite never calls a provider or product service. */
function usageFixture(int $limit = 10, int $warn = 7, ?TenantContext $ctx = null): array
{
    $ctx ??= context();
    [, $intent] = fixture($ctx);
    $attempt = new Attempt('SYNTHETIC_U05_ATTEMPT', $intent, 'TIMEOUT');
    $policy = new MockUsagePolicy('SYNTHETIC_U05_POLICY', $ctx, 'mock.task.create', $limit, $warn, 10, 30);
    $ledger = new MockUsageLedger($policy);
    return [$ctx, $attempt, $policy, $ledger];
}


/** Fill 256 distinct synthetic attempts, using no real credits or providers. */
function f147FillAttempts(MockUsageLedger $ledger, int $measurements = 1): array
{
    [, $intent] = fixture();
    $attempts = [];
    for ($i = 0; $i < 256; ++$i) {
        $attempt = new Attempt('SYNTHETIC_U47_ATTEMPT_' . $i, $intent, 'TIMEOUT');
        $attempts[] = $attempt;
        $ledger->record('SYNTHETIC_U47_EVENT_EST_' . $i,
            $attempt, 'ESTIMADO', 1, 'SYNTHETIC_TASK', 15);
        if ($measurements === 2) {
            $ledger->record('SYNTHETIC_U47_EVENT_OBS_' . $i,
                $attempt, 'OBSERVADO', 1, 'SYNTHETIC_TASK', 15);
        }
    }
    return $attempts;
}

return [
    'U05-01 compatible technical budget is not an authorization grant' => function (): void {
        [$ctx, , , $ledger] = usageFixture();
        check($ledger->budget($ctx, 'mock.task.create', 3, 15)
            === ['state' => 'SYNTHETIC_WITHIN_QUOTA', 'reason' => 'TECHNICAL_ONLY_NOT_AUTHORIZATION']);
        check($ledger->used() === 0 && $ledger->events() === []);
        $gateway = new MockCrmEffectGateway();
        check($gateway->count() === 0);
    },
    'U05-02 exhausted and insufficient fake quota denies new request' => function (): void {
        [$ctx, , , $ledger] = usageFixture(3, 2);
        check($ledger->budget($ctx, 'mock.task.create', 4, 15)['state'] === 'NEGADO');
        [, $attempt] = fixture();
        $a = new Attempt('SYNTHETIC_U05_02_ATTEMPT', $attempt, 'ENVIADA');
        $ledger->record('SYNTHETIC_U05_02_EVENT', $a, 'ESTIMADO', 3, 'SYNTHETIC_QUOTA', 15);
        check($ledger->budget($ctx, 'mock.task.create', 1, 15)['state'] === 'NEGADO');
        [$ctxZero, , , $zero] = usageFixture(0, 0);
        check($zero->budget($ctxZero, 'mock.task.create', 1, 15)['state'] === 'NEGADO');
    },
    'U05-03 exact duplicate usage event counts only once' => function (): void {
        [, $attempt, , $ledger] = usageFixture();
        $first = $ledger->record('SYNTHETIC_U05_03_EVENT', $attempt, 'OBSERVADO', 5, 'SYNTHETIC_MATERIAL', 15);
        $again = $ledger->record('SYNTHETIC_U05_03_EVENT', $attempt, 'OBSERVADO', 5, 'SYNTHETIC_MATERIAL', 15);
        check($first === $again && $ledger->used() === 5 && count($ledger->events()) === 1);
    },
    'U05-04 reused event ID with changed material or units is rejected' => function (): void {
        [, $attempt, , $ledger] = usageFixture();
        $ledger->record('SYNTHETIC_U05_04_EVENT', $attempt, 'OBSERVADO', 3, 'SYNTHETIC_MATERIAL', 15);
        denied('USAGE_EVENT_CONFLICT', fn() => $ledger->record('SYNTHETIC_U05_04_EVENT', $attempt, 'OBSERVADO', 4, 'SYNTHETIC_MATERIAL', 15));
        denied('USAGE_EVENT_CONFLICT', fn() => $ledger->record('SYNTHETIC_U05_04_EVENT', $attempt, 'OBSERVADO', 3, 'SYNTHETIC_CHANGED', 15));
        check($ledger->used() === 3 && count($ledger->events()) === 1);
    },
    'U05-05 unrelated product tenant principal purpose resource cannot share budget' => function (): void {
        [$ctx, , , $ledger] = usageFixture();
        $contexts = [
            new TenantContext('SYNTHETIC_OTHER_PRODUCT', $ctx->tenant, $ctx->principal, $ctx->purpose, $ctx->resource),
            context('SYNTHETIC_B'),
            new TenantContext($ctx->product, $ctx->tenant, 'SYNTHETIC_OTHER_PRINCIPAL', $ctx->purpose, $ctx->resource),
            new TenantContext($ctx->product, $ctx->tenant, $ctx->principal, 'SYNTHETIC_OTHER_PURPOSE', $ctx->resource),
            new TenantContext($ctx->product, $ctx->tenant, $ctx->principal, $ctx->purpose, 'SYNTHETIC_OTHER_RESOURCE'),
        ];
        foreach ($contexts as $other) {
            denied('USAGE_POLICY_DENIED', fn() => $ledger->budget($other, 'mock.task.create', 1, 15));
            [, $intent] = fixture($other);
            $attempt = new Attempt('SYNTHETIC_OTHER_ATTEMPT', $intent, 'ENVIADA');
            denied('USAGE_POLICY_DENIED', fn() => $ledger->record('SYNTHETIC_OTHER_EVENT', $attempt, 'OBSERVADO', 1, 'SYNTHETIC_MATERIAL', 15));
        }
        check($ledger->used() === 0 && $ledger->events() === []);
    },
    'U05-06 missing revoked expired or incompatible fake policy fails closed' => function (): void {
        [$ctx, , $policy, $ledger] = usageFixture();
        denied('USAGE_POLICY_REQUIRED', fn() => (new MockUsageLedger(null))->budget($ctx, 'mock.task.create', 1, 15));
        denied('USAGE_POLICY_DENIED', fn() => $ledger->budget($ctx, 'mock.task.create', 1, 30));
        denied('USAGE_POLICY_DENIED', fn() => $ledger->budget($ctx, 'SYNTHETIC_OTHER_VERSION', 1, 15));
        $policy->revoke();
        denied('USAGE_POLICY_DENIED', fn() => $ledger->budget($ctx, 'mock.task.create', 1, 15));
    },
    'U05-07 estimated observed reconciled evidence is not double-counted' => function (): void {
        [$ctx, $attempt, , $ledger] = usageFixture();
        $ledger->record('SYNTHETIC_U05_07_EST', $attempt, 'ESTIMADO', 4, 'SYNTHETIC_TASK', 15);
        $ledger->record('SYNTHETIC_U05_07_OBS', $attempt, 'OBSERVADO', 5, 'SYNTHETIC_TASK', 15);
        check($ledger->used() === 5);
        check($ledger->reconcile($attempt, null, 15)['state'] === 'INDETERMINADO');
        $proof = new MockUsageProof('SYNTHETIC_PROOF_U05_07', $attempt, 5);
        check($ledger->reconcile($attempt, $proof, 15)['state'] === 'CONCILIADO');
        check($ledger->used() === 5 && $ledger->budget($ctx, 'mock.task.create', 5, 15)['state'] === 'SYNTHETIC_WITHIN_QUOTA');
        check($ledger->reconcile($attempt, $proof, 15)['state'] === 'CONCILIADO');
    },
    'U05-08 late observed technical usage after timeout remains accounted' => function (): void {
        [$ctx, $attempt, , $ledger] = usageFixture();
        $ledger->markUnknown($attempt, 15);
        $ledger->markUnknown($attempt, 15);
        check($ledger->budget($ctx, 'mock.task.create', 1, 15)['state'] === 'INDETERMINADO');
        $ledger->record('SYNTHETIC_U05_08_OBS', $attempt, 'OBSERVADO', 6, 'SYNTHETIC_LATE', 15);
        check($ledger->used() === 6);
        check($ledger->budget($ctx, 'mock.task.create', 1, 15)['state'] === 'INDETERMINADO');
        check(count($ledger->events()) === 2); // uncertain once, then measured; no automatic correction
        check((new MockCrmEffectGateway())->count() === 0);
    },
    'U05-09 missing or contradictory fake proof stays indeterminate' => function (): void {
        [$ctx, $attempt, , $ledger] = usageFixture();
        $ledger->record('SYNTHETIC_U05_09_OBS', $attempt, 'OBSERVADO', 3, 'SYNTHETIC_TASK', 15);
        check($ledger->reconcile($attempt, null, 15) === ['state' => 'INDETERMINADO', 'reason' => 'PROOF_UNAVAILABLE']);
        $wrong = new MockUsageProof('SYNTHETIC_PROOF_WRONG', $attempt, 4);
        check($ledger->reconcile($attempt, $wrong, 15)['state'] === 'INDETERMINADO');
        check($ledger->budget($ctx, 'mock.task.create', 1, 15)['state'] === 'INDETERMINADO');
        $correct = new MockUsageProof('SYNTHETIC_PROOF_CORRECT', $attempt, 3);
        check($ledger->reconcile($attempt, $correct, 15)['state'] === 'INDETERMINADO'); // Contradiction is sticky.
        [, $cleanAttempt, , $cleanLedger] = usageFixture();
        $cleanLedger->record('SYNTHETIC_U05_09_CLEAN_OBS', $cleanAttempt, 'OBSERVADO', 3, 'SYNTHETIC_TASK', 15);
        check($cleanLedger->reconcile($cleanAttempt, new MockUsageProof('SYNTHETIC_PROOF_CLEAR', $cleanAttempt, 3), 15)['state'] === 'CONCILIADO');
    },
    'U05-10 negative huge invalid units and arithmetic limit are denied' => function (): void {
        [$ctx, $attempt, , $ledger] = usageFixture();
        foreach ([-1, 1_000_001, PHP_INT_MAX] as $invalid) {
            denied('USAGE_UNITS_INVALID', fn() => $ledger->record('SYNTHETIC_U05_10_BAD', $attempt, 'OBSERVADO', $invalid, 'SYNTHETIC_TASK', 15));
        }
        denied('USAGE_POLICY_INVALID', fn() => new MockUsagePolicy('SYNTHETIC_BAD', $ctx, 'mock.task.create', 1_000_001, 0, 10, 30));
        denied('USAGE_EVENT_INVALID', fn() => $ledger->record('SYNTHETIC_U05_10_EXTRA', $attempt, 'OBSERVADO', 1, 'SYNTHETIC_' . str_repeat('x', 500), 15));
        try { $ledger->record('SYNTHETIC_U05_10_TYPE', $attempt, 'OBSERVADO', 'not-an-int', 'SYNTHETIC_TASK', 15); throw new RuntimeException('Type check failed'); }
        catch (TypeError $expected) {}
        check($ledger->used() === 0 && $ledger->events() === []);
    },
    'U05-11 threshold and exhaustion alerts are deduplicated without transport' => function (): void {
        [, $attempt, , $ledger] = usageFixture();
        $ledger->record('SYNTHETIC_U05_11_01', $attempt, 'ESTIMADO', 7, 'SYNTHETIC_TASK', 15);
        check(count($ledger->alerts()) === 1 && $ledger->alerts()[0]['code'] === 'SYNTHETIC_NEAR_LIMIT');
        $ledger->record('SYNTHETIC_U05_11_01', $attempt, 'ESTIMADO', 7, 'SYNTHETIC_TASK', 15);
        $other = new Attempt('SYNTHETIC_U05_11_ATTEMPT_2', $attempt->intent, 'RESPOSTA');
        $ledger->record('SYNTHETIC_U05_11_02', $other, 'OBSERVADO', 3, 'SYNTHETIC_TASK', 15);
        check($ledger->used() === 10 && count($ledger->alerts()) === 2);
        check($ledger->alerts()[1]['code'] === 'SYNTHETIC_EXHAUSTED');
        check(array_keys($ledger->alerts()[0]) === ['code', 'policy']);
    },
    'U05-12 injected synthetic secret marker never appears in trace or grants' => function (): void {
        [$ctx, $attempt, , $ledger] = usageFixture();
        $marker = 'SYNTHETIC_token_payload_marker';
        $ledger->record('SYNTHETIC_secret_event_payload_marker', $attempt, 'OBSERVADO', 2, $marker, 15);
        $proof = new MockUsageProof('SYNTHETIC_PROOF_secret_payload_marker', $attempt, 2);
        check($ledger->reconcile($attempt, $proof, 15)['state'] === 'CONCILIADO');
        $trace = $ledger->events();
        check(!str_contains(json_encode($trace, JSON_THROW_ON_ERROR), 'payload_marker'));
        check(array_keys($trace[0]) === ['sequence', 'code', 'event', 'attempt', 'tenant']);
        check($ledger->budget($ctx, 'mock.task.create', 1, 15)['reason'] === 'TECHNICAL_ONLY_NOT_AUTHORIZATION');
    },

    'U47-01 full attempt ledger refuses new measurement without an audit gap' => function (): void {
        [$ctx, , , $ledger] = usageFixture(1000, 900);
        f147FillAttempts($ledger);
        check($ledger->used() === 256 && count($ledger->events()) === 256);
        [, $intent] = fixture();
        $extra = new Attempt('SYNTHETIC_U47_EXTRA', $intent, 'TIMEOUT');
        $snapshot = $ledger->events();
        denied('USAGE_CAPACITY_DENIED', fn() => $ledger->record(
            'SYNTHETIC_U47_NEW_EVENT', $extra, 'OBSERVADO', 1, 'SYNTHETIC_TASK', 15));
        check($ledger->events() === $snapshot && $ledger->used() === 256);
        check($ledger->budget($ctx, 'mock.task.create', 1, 15)
            === ['state' => 'INDETERMINADO', 'reason' => 'MEASUREMENT_CAPACITY_EXCEEDED']);
    },
    'U47-02 exact duplicate event still returns historical usage after cap' => function (): void {
        [, , , $ledger] = usageFixture(1000, 900);
        $attempts = f147FillAttempts($ledger);
        $events = $ledger->events();
        $same = $ledger->record('SYNTHETIC_U47_EVENT_EST_0', $attempts[0],
            'ESTIMADO', 1, 'SYNTHETIC_TASK', 15);
        check($same->units === 1 && $ledger->events() === $events);
        denied('USAGE_EVENT_CONFLICT', fn() => $ledger->record(
            'SYNTHETIC_U47_EVENT_EST_0', $attempts[0],
            'ESTIMADO', 2, 'SYNTHETIC_TASK', 15));
        check($ledger->events() === $events);
    },
    'U47-03 512 material events never silently evict idempotency history' => function (): void {
        [, , , $ledger] = usageFixture(1000, 900);
        $attempts = f147FillAttempts($ledger, 2);
        check(count($ledger->events()) === 512 && $ledger->used() === 256);
        $snapshot = $ledger->events();
        denied('USAGE_CAPACITY_DENIED', fn() => $ledger->record(
            'SYNTHETIC_U47_EVENT_ADJ_0', $attempts[0],
            'AJUSTADO', 1, 'SYNTHETIC_TASK', 15));
        check($ledger->events() === $snapshot && $ledger->used() === 256);
    },
    'U47-04 timeout on a new attempt cannot erase exhausted metering evidence' => function (): void {
        [, , , $ledger] = usageFixture(1000, 900);
        f147FillAttempts($ledger);
        [, $intent] = fixture();
        $extra = new Attempt('SYNTHETIC_U47_TIMEOUT_EXTRA', $intent, 'TIMEOUT');
        $snapshot = $ledger->events();
        denied('USAGE_CAPACITY_DENIED', fn() => $ledger->markUnknown($extra, 15));
        check($ledger->events() === $snapshot);
    },
    'U47-05 contradictory proof cannot allocate a new attempt beyond cap' => function (): void {
        [, , , $ledger] = usageFixture(1000, 900);
        f147FillAttempts($ledger);
        [, $intent] = fixture();
        $extra = new Attempt('SYNTHETIC_U47_PROOF_EXTRA', $intent, 'TIMEOUT');
        $proof = new MockUsageProof('SYNTHETIC_U47_PROOF', $extra, 2);
        $snapshot = $ledger->events();
        denied('USAGE_CAPACITY_DENIED', fn() => $ledger->reconcile($extra, $proof, 15));
        check($ledger->events() === $snapshot);
        check($ledger->reconcile($extra, null, 15)
            === ['state' => 'INDETERMINADO', 'reason' => 'PROOF_UNAVAILABLE']);
    },
    'U47-06 full 1024-entry trace rejects new evidence without losing history' => function (): void {
        [$ctx, , , $ledger] = usageFixture(1000, 900);
        $attempts = f147FillAttempts($ledger, 2);
        foreach ($attempts as $attempt) $ledger->markUnknown($attempt, 15);
        check(count($ledger->events()) === 768);
        foreach ($attempts as $i => $attempt) {
            $proof = new MockUsageProof('SYNTHETIC_U47_WRONG_PROOF_' . $i, $attempt, 2);
            check($ledger->reconcile($attempt, $proof, 15)['reason'] === 'PROOF_CONFLICT');
        }
        check(count($ledger->events()) === 1024);
        $events = $ledger->events();
        denied('USAGE_CAPACITY_DENIED', fn() => $ledger->record(
            'SYNTHETIC_U47_EVENT_ADJ_AFTER_TRACE', $attempts[0],
            'AJUSTADO', 1, 'SYNTHETIC_TASK', 15));
        check($ledger->events() === $events);
        check($ledger->budget($ctx, 'mock.task.create', 1, 15)['state'] === 'INDETERMINADO');
        check(count($ledger->alerts()) <= 2);
    },
];
