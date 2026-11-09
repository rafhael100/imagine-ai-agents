<?php
declare(strict_types=1);

use ImagineAI\Domain\{Approval, Intent, Run};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockResilience\ReadOnlyTraceReview;
use ImagineAI\Mocks\{MockAiSuggestionProvider, MockSupervisedGateway};

/** Two interleaved fictional runs with separate intents; never real CRM data. */
function f21Fixture(): array
{
    $gateway = new MockSupervisedGateway('CONFIRMADO');
    $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
    $runA = $c->start('SYNTHETIC_F21_RUN_A');
    $runB = $c->start('SYNTHETIC_F21_RUN_B');
    $intentA = new Intent('SYNTHETIC_F21_INTENT_A',
        $c->step($runA, 'SYNTHETIC_F21_STEP_A'), 'SYNTHETIC_F21_KEY_A',
        'mock.task.create', 'SYNTHETIC_TASK_A');
    $intentB = new Intent('SYNTHETIC_F21_INTENT_B',
        $c->step($runB, 'SYNTHETIC_F21_STEP_B'), 'SYNTHETIC_F21_KEY_B',
        'mock.task.create', 'SYNTHETIC_TASK_B');
    $approvalA = new Approval('SYNTHETIC_F21_APPROVAL_A',
        $intentA, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
    $approvalB = new Approval('SYNTHETIC_F21_APPROVAL_B',
        $intentB, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
    $proofA = $c->execute($intentA, $approvalA, 15);
    $proofB = $c->execute($intentB, $approvalB, 15);
    return [$c, $gateway, $runA, $runB, $intentA, $intentB, $proofA, $proofB];
}

return [
    'U21-01 mixed global trace cannot be used as single-run proof' => function (): void {
        [$c, $gateway, , , $intentA, , $proofA] = f21Fixture();
        check(count($c->events()) === 8 && $gateway->submitCount() === 2);
        $result = ReadOnlyTraceReview::inspect($c->events(), context(), $intentA, $proofA);
        check($result === ['state' => 'INDETERMINADO', 'reason' => 'CONTEXT_DENIED']);
    },
    'U21-02 per-run A projection is ordered and compatible with its fake proof' => function (): void {
        [$c, , $runA, , $intentA, , $proofA] = f21Fixture();
        $view = $c->eventsForRun($runA);
        check(count($view) === 4 && array_column($view, 'sequence') === [1, 2, 3, 4]);
        check(array_column($view, 'code') === [
            'SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED',
            'SYNTHETIC_SUBMITTED', 'SYNTHETIC_CONFIRMADO',
        ]);
        check(ReadOnlyTraceReview::inspect($view, context(), $intentA, $proofA)['state'] === 'CONFIRMADO');
    },
    'U21-03 per-run B projection is independent of run A' => function (): void {
        [$c, , , $runB, , $intentB, , $proofB] = f21Fixture();
        $view = $c->eventsForRun($runB);
        check(count($view) === 4 && array_column($view, 'sequence') === [1, 2, 3, 4]);
        check(ReadOnlyTraceReview::inspect($view, context(), $intentB, $proofB)['state'] === 'CONFIRMADO');
        foreach ($view as $event) {
            check($event['run'] === 'SYNTHETIC_' . hash('sha256', $runB->id));
        }
    },
    'U21-04 another synthetic tenant cannot request the run projection' => function (): void {
        [$c, , $runA] = f21Fixture();
        $foreign = new Run($runA->id, context('SYNTHETIC_B'));
        denied('CONTEXT_DENIED', fn() => $c->eventsForRun($foreign));
        check(count($c->events()) === 8);
    },
    'U21-05 unknown run returns no events and no synthetic confirmation' => function (): void {
        [$c, $gateway] = f21Fixture();
        $unknown = new Run('SYNTHETIC_F21_UNKNOWN', context());
        $view = $c->eventsForRun($unknown);
        check($view === [] && $gateway->submitCount() === 2);
        check(ReadOnlyTraceReview::inspect($view, context())
            === ['state' => 'INDETERMINADO', 'reason' => 'TRACE_INVALID']);
    },
    'U21-06 modifying a returned projection never mutates global history' => function (): void {
        [$c, , $runA] = f21Fixture();
        $before = $c->events();
        $view = $c->eventsForRun($runA);
        $view[0]['code'] = 'SYNTHETIC_TAMPERED';
        $view[1]['sequence'] = 999;
        check($c->events() === $before);
        check($c->eventsForRun($runA)[0]['code'] === 'SYNTHETIC_STARTED');
    },
    'U21-07 status query for A does not contaminate B projection' => function (): void {
        [$c, $gateway, $runA, $runB, $intentA, $intentB, $proofA, $proofB] = f21Fixture();
        check($c->reconcile($intentA) === $proofA);
        check(count($c->eventsForRun($runA)) === 5
            && count($c->eventsForRun($runB)) === 4);
        check(ReadOnlyTraceReview::inspect($c->eventsForRun($runA), context(), $intentA, $proofA)['state'] === 'CONFIRMADO');
        check(ReadOnlyTraceReview::inspect($c->eventsForRun($runB), context(), $intentB, $proofB)['state'] === 'CONFIRMADO');
        check($gateway->submitCount() === 2 && $gateway->statusCount() === 1);
    },
    'U21-08 canceled run remains readable without any new dispatch' => function (): void {
        [$c, $gateway, $runA, , $intentA, , $proofA] = f21Fixture();
        $c->transition($runA, 'CANCELADA');
        $view = $c->eventsForRun($runA);
        check(count($view) === 5 && $view[4]['code'] === 'SYNTHETIC_RUN_CHANGED');
        check(ReadOnlyTraceReview::inspect($view, context(), $intentA, $proofA)['state'] === 'CONFIRMADO');
        check($gateway->submitCount() === 2);
    },
];
