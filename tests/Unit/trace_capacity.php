<?php
declare(strict_types=1);

use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\{Approval, Attempt, Intent, Run};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockResilience\ReadOnlyTraceReview;
use ImagineAI\Mocks\MockSupervisedGateway;

/** Small process-local counting spy. It never invokes a model, API or tool. */
final class F20CountingAi implements AiSuggestionProvider
{
    public int $calls = 0;

    public function suggest(Run $run, array $fields): array
    {
        ++$this->calls;
        return ['kind' => 'SYNTHETIC_SUGGESTION', 'version' => 'mock-v1'];
    }
}

/** Fixtures use only deterministic fake references and no external services. */
function f20Fixture(): array
{
    $gateway = new MockSupervisedGateway('CONFIRMADO');
    $ai = new F20CountingAi();
    $c = new SupervisedCoordinator(context(), $gateway, $ai);
    $run = $c->start('SYNTHETIC_CAPACITY_RUN');
    return [$c, $run, $gateway, $ai];
}

function f20Fill(SupervisedCoordinator $c, Run $run, int $until): void
{
    while (count($c->events()) < $until) {
        $c->suggest($run, []);
    }
    check(count($c->events()) === $until);
}

function f20Intent(SupervisedCoordinator $c, Run $run): array
{
    $intent = new Intent('SYNTHETIC_CAPACITY_INTENT',
        $c->step($run, 'SYNTHETIC_CAPACITY_STEP'),
        'SYNTHETIC_CAPACITY_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
    $approval = new Approval('SYNTHETIC_CAPACITY_APPROVAL', $intent,
        'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
    return [$intent, $approval];
}

return [
    'U20-01 trace accepts exactly 1024 events and rejects next suggestion before AI' => function (): void {
        [$c, $run, $gateway, $ai] = f20Fixture();
        f20Fill($c, $run, 1024);
        check($ai->calls === 1023);
        check(ReadOnlyTraceReview::inspect($c->events(), context())['reason'] === 'PROOF_UNAVAILABLE');
        $snapshot = $c->events();
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->suggest($run, []));
        check($c->events() === $snapshot && $ai->calls === 1023 && $gateway->submitCount() === 0);
    },
    'U20-02 pending submission requires space for both send and result' => function (): void {
        [$c, $run, $gateway, $ai] = f20Fixture();
        [$intent, $approval] = f20Intent($c, $run);
        f20Fill($c, $run, 1023);
        $snapshot = $c->events();
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->execute($intent, $approval, 15));
        check($c->events() === $snapshot && $gateway->submitCount() === 0);
    },
    'U20-03 exact two available slots permit one confirmed submission' => function (): void {
        [$c, $run, $gateway, $ai] = f20Fixture();
        [$intent, $approval] = f20Intent($c, $run);
        f20Fill($c, $run, 1022);
        $proof = $c->execute($intent, $approval, 15);
        check($proof->isConfirmed() && count($c->events()) === 1024);
        check($gateway->submitCount() === 1
            && ReadOnlyTraceReview::inspect($c->events(), context(), $intent, $proof)['state'] === 'CONFIRMADO');
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->reconcile($intent));
        check($gateway->statusCount() === 0 && $gateway->submitCount() === 1);
    },
    'U20-04 status is not queried unless there is room for response evidence' => function (): void {
        [$c, $run, $gateway, $ai] = f20Fixture();
        [$intent, $approval] = f20Intent($c, $run);
        $c->execute($intent, $approval, 15);
        f20Fill($c, $run, 1023);
        $snapshot = $c->events();
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->reconcile($intent));
        check($c->events() === $snapshot && $gateway->statusCount() === 0
            && $gateway->submitCount() === 1);
    },
    'U20-05 recovery requires slots for timeout status query and proof' => function (): void {
        [$c, $run, $gateway, $ai] = f20Fixture();
        [$intent, $approval] = f20Intent($c, $run);
        $c->execute($intent, $approval, 15);
        f20Fill($c, $run, 1022);
        $snapshot = $c->events();
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->afterTimeout(
            $intent, new Attempt('SYNTHETIC_CAPACITY_TIMEOUT', $intent, 'TIMEOUT')));
        check($c->events() === $snapshot && $gateway->statusCount() === 0
            && $gateway->submitCount() === 1);
    },
    'U20-06 terminal transition does not mutate a run without trace capacity' => function (): void {
        [$c, $run, $gateway, $ai] = f20Fixture();
        f20Fill($c, $run, 1024);
        $snapshot = $c->events();
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->transition($run, 'CANCELADA'));
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->step($run, 'SYNTHETIC_NEXT_STEP'));
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->start('SYNTHETIC_NEW_RUN'));
        check($run->state() === 'EXECUTANDO' && $c->events() === $snapshot
            && $gateway->submitCount() === 0);
    },
    'U20-07 rejection event fills final slot without effect dispatch' => function (): void {
        [$c, $run, $gateway, $ai] = f20Fixture();
        [$intent, $approval] = f20Intent($c, $run);
        f20Fill($c, $run, 1023);
        denied('APPROVAL_REQUIRED', fn() => $c->execute($intent, null, 15));
        check(count($c->events()) === 1024 && $gateway->submitCount() === 0);
        $last = $c->events()[1023];
        check($last['code'] === 'SYNTHETIC_APPROVAL_DENIED');
        denied('TRACE_CAPACITY_EXCEEDED', fn() => $c->execute($intent, $approval, 15));
        check($gateway->submitCount() === 0);
    },
];
