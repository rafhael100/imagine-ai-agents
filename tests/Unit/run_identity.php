<?php
declare(strict_types=1);
use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\{Approval, Intent, Run, Step};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockResilience\ReadOnlyTraceReview;
use ImagineAI\Mocks\{MockAiSuggestionProvider, MockSupervisedGateway};

final class F22CountingAi implements AiSuggestionProvider {
    public int $calls = 0;
    public function suggest(Run $run, array $fields): array {
        ++$this->calls;
        return ['kind' => 'SYNTHETIC_SUGGESTION'];
    }
}
function f22(): array {
    $gateway = new MockSupervisedGateway('CONFIRMADO');
    $ai = new F22CountingAi();
    $c = new SupervisedCoordinator(context(), $gateway, $ai);
    return [$c, $c->start('SYNTHETIC_F22_RUN'), $gateway, $ai];
}
function f22intent(SupervisedCoordinator $c, Run $run): Intent {
    return new Intent('SYNTHETIC_F22_INTENT', $c->step($run, 'SYNTHETIC_F22_STEP'),
        'SYNTHETIC_F22_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
}
function f22approval(Intent $intent): Approval {
    return new Approval('SYNTHETIC_F22_APPROVAL', $intent, 'SYNTHETIC_REVIEWER',
        10, 20, 'APROVADA');
}
function f22cloneRun(Run $run): Run {
    $other = new Run($run->id, context());
    $other->transition('EXECUTANDO');
    return $other;
}

return [
    'U22-01 duplicate start ID never duplicates STARTED event' => function (): void {
        [$c, $run, $gateway] = f22();
        $events = $c->events();
        denied('RUN_ID_CONFLICT', fn() => $c->start($run->id));
        check($c->events() === $events && $gateway->submitCount() === 0);
    },
    'U22-02 canceled run ID cannot be recycled' => function (): void {
        [$c, $run] = f22();
        $c->transition($run, 'CANCELADA');
        $events = $c->events();
        denied('RUN_ID_CONFLICT', fn() => $c->start($run->id));
        check($c->events() === $events && $run->state() === 'CANCELADA');
    },
    'U22-03 different run IDs still have independent projections' => function (): void {
        [$c, $run] = f22();
        $other = $c->start('SYNTHETIC_F22_SECOND');
        check($run !== $other && count($c->eventsForRun($run)) === 1
            && count($c->eventsForRun($other)) === 1 && count($c->events()) === 2);
    },
    'U22-04 same-ID detached run cannot retrieve another run projection' => function (): void {
        [$c, $run] = f22();
        denied('RUN_ID_CONFLICT', fn() => $c->eventsForRun(f22cloneRun($run)));
        check(count($c->eventsForRun($run)) === 1);
    },
    'U22-05 detached same-ID run cannot create a recorded step' => function (): void {
        [$c, $run, $gateway] = f22();
        $before = $c->events();
        denied('RUN_NOT_OWNED', fn() => $c->step(f22cloneRun($run), 'SYNTHETIC_F22_NEW'));
        check($c->events() === $before && $gateway->submitCount() === 0);
    },
    'U22-06 detached run cannot call the synthetic AI provider' => function (): void {
        [$c, $run, $gateway, $ai] = f22();
        denied('RUN_NOT_OWNED', fn() => $c->suggest(f22cloneRun($run), []));
        check($ai->calls === 0 && $gateway->submitCount() === 0 && count($c->events()) === 1);
    },
    'U22-07 detached run cannot transition the registered lifecycle' => function (): void {
        [$c, $run] = f22();
        $detached = f22cloneRun($run);
        $before = $c->events();
        denied('RUN_NOT_OWNED', fn() => $c->transition($detached, 'CANCELADA'));
        check($run->state() === 'EXECUTANDO'
            && $detached->state() === 'EXECUTANDO' && $c->events() === $before);
    },
    'U22-08 detached same-ID intent cannot be submitted by coordinator' => function (): void {
        [$c, $run, $gateway] = f22();
        $other = f22cloneRun($run);
        $intent = new Intent('SYNTHETIC_F22_INTENT', $other->step('SYNTHETIC_F22_STEP'),
            'SYNTHETIC_F22_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        denied('RUN_NOT_OWNED', fn() => $c->execute($intent, f22approval($intent), 15));
        check($gateway->submitCount() === 0 && count($c->events()) === 1);
    },
    'U22-09 detached same-ID intent cannot query registered status' => function (): void {
        [$c, $run, $gateway] = f22();
        $valid = f22intent($c, $run);
        $c->execute($valid, f22approval($valid), 15);
        $other = f22cloneRun($run);
        $forged = new Intent($valid->id, $other->step($valid->step->id),
            $valid->idempotencyKey, $valid->operation, $valid->material);
        $events = $c->events();
        denied('RUN_NOT_OWNED', fn() => $c->reconcile($forged));
        check($c->events() === $events && $gateway->statusCount() === 0);
    },
    'U22-10 duplicate step ID cannot produce a second STEP_CREATED' => function (): void {
        [$c, $run, $gateway] = f22();
        $step = $c->step($run, 'SYNTHETIC_F22_STEP');
        $events = $c->events();
        denied('STEP_ID_CONFLICT', fn() => $c->step($run, $step->id));
        check($c->events() === $events && $gateway->submitCount() === 0);
    },
    'U22-11 externally constructed same-ID Step cannot borrow an approval' => function (): void {
        [$c, $run, $gateway] = f22();
        $valid = f22intent($c, $run);
        $forged = new Intent($valid->id, new Step($valid->step->id, $run),
            $valid->idempotencyKey, $valid->operation, $valid->material);
        check($valid->signature() === $forged->signature());
        $events = $c->events();
        denied('STEP_NOT_OWNED', fn() => $c->execute($forged, f22approval($valid), 15));
        check($c->events() === $events && $gateway->submitCount() === 0);
    },
    'U22-12 legitimate registered run and step still work normally' => function (): void {
        [$c, $run, $gateway] = f22();
        $intent = f22intent($c, $run);
        $effect = $c->execute($intent, f22approval($intent), 15);
        check($effect->isConfirmed() && $gateway->submitCount() === 1);
        check(ReadOnlyTraceReview::inspect(
            $c->eventsForRun($run), context(), $intent, $effect)['state'] === 'CONFIRMADO');
        check($c->reconcile($intent) === $effect && $gateway->statusCount() === 1);
    },
    'U22-13 unknown run cannot join a coordinator with registered runs' => function (): void {
        [$c, , $gateway, $ai] = f22();
        $unknown = new Run('SYNTHETIC_F22_UNKNOWN', context());
        $unknown->transition('EXECUTANDO');
        denied('RUN_NOT_OWNED', fn() => $c->step($unknown, 'SYNTHETIC_F22_STEP'));
        denied('RUN_NOT_OWNED', fn() => $c->suggest($unknown, []));
        check($c->eventsForRun($unknown) === [] && count($c->events()) === 1);
        check($gateway->submitCount() === 0 && $ai->calls === 0);
    },
    'U22-14 standalone legacy mock path remains compatible' => function (): void {
        $gateway = new MockSupervisedGateway('CONFIRMADO');
        $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
        $run = new Run('SYNTHETIC_F22_STANDALONE', context());
        $run->transition('EXECUTANDO');
        $intent = new Intent('SYNTHETIC_F22_INTENT', $run->step('SYNTHETIC_F22_STEP'),
            'SYNTHETIC_F22_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        check($c->execute($intent, f22approval($intent), 15)->isConfirmed());
        check($gateway->submitCount() === 1 && $c->eventsForRun($run) === []);
    },
    'U22-15 tenant mismatch still denies before disclosing any view' => function (): void {
        [$c, $run] = f22();
        $wrong = new Run($run->id, context('SYNTHETIC_B'));
        denied('CONTEXT_DENIED', fn() => $c->eventsForRun($wrong));
        denied('CONTEXT_DENIED', fn() => $c->step($wrong, 'SYNTHETIC_F22_STEP'));
        check(count($c->events()) === 1);
    },
    'U22-16 changed material on the canonical Step still conflicts by idempotency' => function (): void {
        [$c, $run, $gateway] = f22();
        $valid = f22intent($c, $run);
        $effect = $c->execute($valid, f22approval($valid), 15);
        $changed = new Intent($valid->id, $valid->step, $valid->idempotencyKey,
            $valid->operation, 'SYNTHETIC_CHANGED_MATERIAL');
        $snapshot = $c->events();
        denied('IDEMPOTENCY_CONFLICT', fn() => $c->execute($changed, f22approval($changed), 15));
        check($effect->isConfirmed() && $c->events() === $snapshot
            && $gateway->submitCount() === 1 && $gateway->statusCount() === 0);
    },
];
