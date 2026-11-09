<?php
declare(strict_types=1);

use ImagineAI\Contracts\CrmEffectGateway;
use ImagineAI\Domain\{Approval, Attempt, Effect, Intent, TenantContext};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockResilience\ReadOnlyTraceReview;
use ImagineAI\Mocks\{MockAiSuggestionProvider, MockSupervisedGateway};

/** Offline-only fake with a deliberately sensitive-looking diagnostic marker. */
final class F23ThrowingStatusGateway implements CrmEffectGateway
{
    private MockSupervisedGateway $inner;
    public int $statusCalls = 0;
    public function __construct()
    {
        $this->inner = new MockSupervisedGateway('CONFIRMADO');
    }
    public function submit(Intent $intent, ?Approval $approval, int $now): Effect
    {
        return $this->inner->submit($intent, $approval, $now);
    }
    public function status(TenantContext $context, Intent $intent): Effect
    {
        $context->assertSame($intent->context);
        ++$this->statusCalls;
        throw new RuntimeException('SYNTHETIC_SENSITIVE_DIAGNOSTIC_MARKER');
    }
    public function submitCount(): int { return $this->inner->submitCount(); }
}

/** Simulates an acknowledged dispatch accompanied by a proof for a different intent. */
final class F24MismatchedSubmitGateway implements CrmEffectGateway
{
    private MockSupervisedGateway $inner;
    public function __construct()
    {
        $this->inner = new MockSupervisedGateway('CONFIRMADO');
    }
    public function submit(Intent $intent, ?Approval $approval, int $now): Effect
    {
        // The mock records the actual target, but the response binds to another
        // intent. The coordinator must not accept this as proof of the target.
        $this->inner->submit($intent, $approval, $now);
        $wrong = new Intent('SYNTHETIC_F24_WRONG_INTENT', $intent->step,
            'SYNTHETIC_F24_WRONG_KEY', $intent->operation,
            'SYNTHETIC_F24_PRIVATE_DIAGNOSTIC');
        return new Effect($wrong, 'NEGADO');
    }
    public function status(TenantContext $context, Intent $intent): Effect
    {
        return $this->inner->status($context, $intent);
    }
    public function submitCount(): int { return $this->inner->submitCount(); }
    public function statusCount(): int { return $this->inner->statusCount(); }
}

function f24Fixture(): array
{
    $context = context();
    $gateway = new F24MismatchedSubmitGateway();
    $c = new SupervisedCoordinator($context, $gateway, new MockAiSuggestionProvider());
    $run = $c->start('SYNTHETIC_F24_RUN');
    $intent = new Intent('SYNTHETIC_F24_INTENT', $c->step($run, 'SYNTHETIC_F24_STEP'),
        'SYNTHETIC_F24_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
    $approval = new Approval('SYNTHETIC_F24_APPROVAL', $intent,
        'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
    return [$context, $gateway, $c, $intent, $approval];
}

/** Delivers a submitted-only acknowledgment with no verified effect. */
final class F25SubmittedOnlyGateway implements CrmEffectGateway
{
    private MockSupervisedGateway $inner;
    public function __construct(private readonly bool $onStatus = false)
    {
        $this->inner = new MockSupervisedGateway('CONFIRMADO');
    }
    public function submit(Intent $intent, ?Approval $approval, int $now): Effect
    {
        $proof = $this->inner->submit($intent, $approval, $now);
        return $this->onStatus ? $proof : new Effect($intent, 'SOLICITADO');
    }
    public function status(TenantContext $context, Intent $intent): Effect
    {
        $proof = $this->inner->status($context, $intent);
        return $this->onStatus ? new Effect($intent, 'SOLICITADO') : $proof;
    }
    public function submitCount(): int { return $this->inner->submitCount(); }
    public function statusCount(): int { return $this->inner->statusCount(); }
}

function f25Fixture(bool $onStatus): array
{
    $ctx = context();
    $gateway = new F25SubmittedOnlyGateway($onStatus);
    $c = new SupervisedCoordinator($ctx, $gateway, new MockAiSuggestionProvider());
    $run = $c->start('SYNTHETIC_F25_RUN');
    $intent = new Intent('SYNTHETIC_F25_INTENT', $c->step($run, 'SYNTHETIC_F25_STEP'),
        'SYNTHETIC_F25_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
    $approval = new Approval('SYNTHETIC_F25_APPROVAL', $intent,
        'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
    return [$ctx, $gateway, $c, $intent, $approval];
}

return [
    'U23-01 confirmed proof survives a later unavailable status response' => function (): void {
        [$ctx, $gateway, $c, , $intent, $approval] = resilienceFixture([], [true]);
        $effect = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->reconcile($intent));
        $events = $c->events();
        check(array_slice(array_column($events, 'code'), -2) === [
            'SYNTHETIC_STATUS_REQUESTED', 'SYNTHETIC_STATUS_UNAVAILABLE',
        ]);
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['state'] === 'CONFIRMADO');
    },
    'U23-02 previously indeterminate proof remains indeterminate on status outage' => function (): void {
        [$ctx, $gateway, $c, , $intent, $approval] = resilienceFixture([true], [true]);
        $effect = $c->execute($intent, $approval, 15);
        check($effect->state === 'INDETERMINADO');
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->reconcile($intent));
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $effect)['state'] === 'INDETERMINADO');
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
    },
    'U23-03 timeout and failed recovery query are both accounted for' => function (): void {
        [$ctx, $gateway, $c, , $intent, $approval] = resilienceFixture([true], [true]);
        $effect = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->afterTimeout(
            $intent, new Attempt('SYNTHETIC_F23_TIMEOUT', $intent, 'TIMEOUT')));
        check(array_slice(array_column($c->events(), 'code'), -3) === [
            'SYNTHETIC_TIMEOUT', 'SYNTHETIC_STATUS_REQUESTED', 'SYNTHETIC_STATUS_UNAVAILABLE',
        ]);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $effect)['state'] === 'INDETERMINADO');
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
    },
    'U23-04 injected status outage without a query is rejected' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture();
        $effect = $c->execute($intent, $approval, 15);
        $events = $c->events();
        $insert = $events[2];
        $insert['code'] = 'SYNTHETIC_STATUS_UNAVAILABLE';
        array_splice($events, 3, 0, [$insert]);
        foreach ($events as $i => &$event) $event['sequence'] = $i + 1;
        unset($event);
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['reason'] === 'STATE_CONFLICT');
    },
    'U23-05 fabricated status outage before submit cannot be evidence' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture();
        $effect = $c->execute($intent, $approval, 15);
        $events = $c->events();
        $insert = $events[2];
        $insert['code'] = 'SYNTHETIC_STATUS_UNAVAILABLE';
        array_splice($events, 2, 0, [$insert]);
        foreach ($events as $i => &$event) $event['sequence'] = $i + 1;
        unset($event);
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['reason'] === 'STATE_CONFLICT');
    },
    'U23-06 duplicate unavailable response cannot close two queries' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture([], [true]);
        $effect = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->reconcile($intent));
        $events = $c->events();
        $duplicate = $events[count($events) - 1];
        $duplicate['sequence'] = count($events) + 1;
        $events[] = $duplicate;
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['reason'] === 'STATE_CONFLICT');
    },
    'U23-07 unterminated status request is not a complete proof trail' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture([], [true]);
        $effect = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->reconcile($intent));
        $events = $c->events();
        array_pop($events);
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['reason'] === 'STATE_CONFLICT');
    },
    'U23-08 unrelated event between status request and outage is denied' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture([], [true]);
        $effect = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->reconcile($intent));
        $events = $c->events();
        $insert = $events[0];
        $insert['code'] = 'SYNTHETIC_SUGGESTED';
        array_splice($events, count($events) - 1, 0, [$insert]);
        foreach ($events as $i => &$event) $event['sequence'] = $i + 1;
        unset($event);
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['reason'] === 'STATE_CONFLICT');
    },
    'U23-09 arbitrary exception detail never enters the synthetic audit' => function (): void {
        $gateway = new F23ThrowingStatusGateway();
        $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
        $run = $c->start('SYNTHETIC_F23_RUN');
        $intent = new Intent('SYNTHETIC_F23_INTENT', $c->step($run, 'SYNTHETIC_F23_STEP'),
            'SYNTHETIC_F23_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $approval = new Approval('SYNTHETIC_F23_APPROVAL', $intent,
            'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $proof = $c->execute($intent, $approval, 15);
        try { $c->reconcile($intent); throw new RuntimeException('SYNTHETIC_UNEXPECTED_SUCCESS'); }
        catch (RuntimeException $e) {
            check($e->getMessage() === 'SYNTHETIC_SENSITIVE_DIAGNOSTIC_MARKER');
        }
        $serialized = json_encode($c->events(), JSON_THROW_ON_ERROR);
        check(!str_contains($serialized, 'SENSITIVE_DIAGNOSTIC_MARKER'));
        check($gateway->statusCalls === 1 && $gateway->submitCount() === 1);
        check(ReadOnlyTraceReview::inspect($c->events(), context(), $intent, $proof)['state'] === 'CONFIRMADO');
    },
    'U23-10 repeated execute after submit only checks status and records failure' => function (): void {
        [$ctx, $gateway, $c, , $intent, $approval] = resilienceFixture([], [true]);
        $effect = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->execute($intent, $approval, 15));
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
        check(array_slice(array_column($c->events(), 'code'), -2) === [
            'SYNTHETIC_STATUS_REQUESTED', 'SYNTHETIC_STATUS_UNAVAILABLE',
        ]);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $effect)['state'] === 'CONFIRMADO');
    },
    'U23-11 missing intent signature on outage event is rejected' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture([], [true]);
        $effect = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->reconcile($intent));
        $events = $c->events();
        unset($events[count($events) - 1]['intent_signature']);
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['reason'] === 'INTENT_CONFLICT');
    },
    'U23-12 later confirmed status closes outage without second submission' => function (): void {
        [$ctx, $gateway, $c, , $intent, $approval] = resilienceFixture([true], [true, false]);
        $initial = $c->execute($intent, $approval, 15);
        check($initial->state === 'INDETERMINADO');
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->afterTimeout(
            $intent, new Attempt('SYNTHETIC_F23_RETRY_TIMEOUT', $intent, 'TIMEOUT')));
        $confirmed = $c->reconcile($intent);
        check($confirmed->isConfirmed() && $gateway->submitCount() === 1
            && $gateway->statusCount() === 2);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $confirmed)['state'] === 'CONFIRMADO');
        check(count(array_filter($c->events(), static fn(array $event): bool =>
            $event['code'] === 'SYNTHETIC_STATUS_UNAVAILABLE')) === 1);
    },
    'U23-13 successful terminal status recheck closes the trace without resubmission' => function (): void {
        [$ctx, $gateway, $c, , $intent, $approval] = resilienceFixture();
        $original = $c->execute($intent, $approval, 15);
        $before = count($c->events());
        check($c->reconcile($intent) === $original);
        check(count($c->events()) === $before + 2);
        check(array_slice(array_column($c->events(), 'code'), -2) === [
            'SYNTHETIC_STATUS_REQUESTED', 'SYNTHETIC_CONFIRMADO',
        ]);
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $original)['state'] === 'CONFIRMADO');
    },
    'U23-14 denied terminal status is closed without another submit' => function (): void {
        $gateway = new MockSupervisedGateway('NEGADO');
        $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
        $run = $c->start('SYNTHETIC_F23_DENIED_RUN');
        $intent = new Intent('SYNTHETIC_F23_DENIED_INTENT',
            $c->step($run, 'SYNTHETIC_F23_DENIED_STEP'),
            'SYNTHETIC_F23_DENIED_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $approval = new Approval('SYNTHETIC_F23_DENIED_APPROVAL',
            $intent, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $original = $c->execute($intent, $approval, 15);
        check($original->state === 'NEGADO' && !$original->isConfirmed());
        check($c->reconcile($intent) === $original);
        check(array_slice(array_column($c->events(), 'code'), -2) === [
            'SYNTHETIC_STATUS_REQUESTED', 'SYNTHETIC_NEGADO',
        ]);
        check(ReadOnlyTraceReview::inspect($c->events(), context(), $intent, $original)['state'] === 'NEGADO');
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
    },
    'U23-15 contradictory terminal status is rejected and closes audit without resubmit' => function (): void {
        $gateway = new class implements CrmEffectGateway {
            private MockSupervisedGateway $inner;
            public int $statusCalls = 0;
            public function __construct() { $this->inner = new MockSupervisedGateway('CONFIRMADO'); }
            public function submit(Intent $intent, ?Approval $approval, int $now): Effect
            {
                return $this->inner->submit($intent, $approval, $now);
            }
            public function status(TenantContext $context, Intent $intent): Effect
            {
                $context->assertSame($intent->context);
                ++$this->statusCalls;
                return new Effect($intent, 'NEGADO');
            }
            public function submitCount(): int { return $this->inner->submitCount(); }
        };
        $ctx = context();
        $c = new SupervisedCoordinator($ctx, $gateway, new MockAiSuggestionProvider());
        $run = $c->start('SYNTHETIC_F23_CONFLICT_RUN');
        $intent = new Intent('SYNTHETIC_F23_CONFLICT_INTENT',
            $c->step($run, 'SYNTHETIC_F23_CONFLICT_STEP'),
            'SYNTHETIC_F23_CONFLICT_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $approval = new Approval('SYNTHETIC_F23_CONFLICT_APPROVAL',
            $intent, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $original = $c->execute($intent, $approval, 15);
        denied('STATE_CONFLICT', fn() => $c->reconcile($intent));
        check($gateway->submitCount() === 1 && $gateway->statusCalls === 1);
        check(array_slice(array_column($c->events(), 'code'), -2) === [
            'SYNTHETIC_STATUS_REQUESTED', 'SYNTHETIC_STATUS_REJECTED',
        ]);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $original)['state'] === 'CONFIRMADO');
    },
    'U23-16 forged status rejection without request fails closed' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture();
        $effect = $c->execute($intent, $approval, 15);
        $events = $c->events();
        $fake = $events[count($events) - 1];
        $fake['code'] = 'SYNTHETIC_STATUS_REJECTED';
        $fake['sequence'] = count($events) + 1;
        $events[] = $fake;
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $effect)['reason'] === 'STATE_CONFLICT');
    },

    'U24-01 mismatched submit response stays uncertain and trace closes' => function (): void {
        [$ctx, $gateway, $c, $intent, $approval] = f24Fixture();
        $effect = $c->execute($intent, $approval, 15);
        check($effect->state === 'INDETERMINADO' && !$effect->isConfirmed());
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 0);
        check(array_slice(array_column($c->events(), 'code'), -2) === [
            'SYNTHETIC_SUBMITTED', 'SYNTHETIC_INDETERMINADO',
        ]);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $effect)['state'] === 'INDETERMINADO');
    },
    'U24-02 repeated execute uses status only to recover valid proof' => function (): void {
        [$ctx, $gateway, $c, $intent, $approval] = f24Fixture();
        check($c->execute($intent, $approval, 15)->state === 'INDETERMINADO');
        $confirmed = $c->execute($intent, $approval, 15);
        check($confirmed->isConfirmed());
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $confirmed)['state'] === 'CONFIRMADO');
    },
    'U24-03 wrong returned intent never enters minimized event payload' => function (): void {
        [$ctx, $gateway, $c, $intent, $approval] = f24Fixture();
        $effect = $c->execute($intent, $approval, 15);
        $trace = json_encode($c->events(), JSON_THROW_ON_ERROR);
        check(!str_contains($trace, 'SYNTHETIC_F24_WRONG_INTENT'));
        check(!str_contains($trace, 'SYNTHETIC_F24_PRIVATE_DIAGNOSTIC'));
        check($gateway->submitCount() === 1);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $effect)['reason'] === 'SYNTHETIC_PROOF_ONLY');
    },

    'U25-01 submit acknowledgement alone cannot become effect proof' => function (): void {
        [$ctx, $gateway, $c, $intent, $approval] = f25Fixture(false);
        $unknown = $c->execute($intent, $approval, 15);
        check($unknown->state === 'INDETERMINADO' && !$unknown->isConfirmed());
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 0);
        check(array_slice(array_column($c->events(), 'code'), -2) === [
            'SYNTHETIC_SUBMITTED', 'SYNTHETIC_INDETERMINADO',
        ]);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $unknown)['state'] === 'INDETERMINADO');
    },
    'U25-02 only a subsequent status check can confirm the submitted operation' => function (): void {
        [$ctx, $gateway, $c, $intent, $approval] = f25Fixture(false);
        check($c->execute($intent, $approval, 15)->state === 'INDETERMINADO');
        $confirmed = $c->execute($intent, $approval, 15);
        check($confirmed->isConfirmed() && $gateway->submitCount() === 1 && $gateway->statusCount() === 1);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $confirmed)['state'] === 'CONFIRMADO');
    },
    'U25-03 status acknowledgement cannot downgrade prior confirmed proof' => function (): void {
        [$ctx, $gateway, $c, $intent, $approval] = f25Fixture(true);
        $original = $c->execute($intent, $approval, 15);
        check($original->isConfirmed());
        denied('STATE_CONFLICT', fn() => $c->reconcile($intent));
        check($gateway->submitCount() === 1 && $gateway->statusCount() === 1);
        check(array_slice(array_column($c->events(), 'code'), -2) === [
            'SYNTHETIC_STATUS_REQUESTED', 'SYNTHETIC_STATUS_REJECTED',
        ]);
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $original)['state'] === 'CONFIRMADO');
    },

    'U26-01 repeated confirmation without status request cannot count as proof' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture();
        $original = $c->execute($intent, $approval, 15);
        $events = $c->events();
        $fake = $events[count($events) - 1];
        $fake['sequence'] = count($events) + 1;
        $events[] = $fake;
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $original)['reason'] === 'STATE_CONFLICT');
    },
    'U26-02 new pending outcome without query cannot overwrite earlier uncertainty' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture([true]);
        $original = $c->execute($intent, $approval, 15);
        $events = $c->events();
        $fake = $events[count($events) - 1];
        $fake['sequence'] = count($events) + 1;
        $fake['code'] = 'SYNTHETIC_PENDENTE';
        $events[] = $fake;
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $original)['reason'] === 'STATE_CONFLICT');
    },
    'U26-03 outage cannot silently authorize a later effect event without new request' => function (): void {
        [$ctx, , $c, , $intent, $approval] = resilienceFixture([], [true]);
        $original = $c->execute($intent, $approval, 15);
        denied('SYNTHETIC_STATUS_UNAVAILABLE', fn() => $c->reconcile($intent));
        $events = $c->events();
        $fake = $events[3];
        $fake['sequence'] = count($events) + 1;
        $events[] = $fake;
        check(ReadOnlyTraceReview::inspect($events, $ctx, $intent, $original)['reason'] === 'STATE_CONFLICT');
        check(ReadOnlyTraceReview::inspect($c->events(), $ctx, $intent, $original)['state'] === 'CONFIRMADO');
    },
];
