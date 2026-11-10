<?php
declare(strict_types=1);

use ImagineAI\Domain\{Approval, Intent, Run};
use ImagineAI\Mocks\{MockAiSuggestionProvider, MockCrmEffectGateway, MockSupervisedGateway};
use ImagineAI\MockOrchestration\SupervisedCoordinator;

/** Synthetic-only fixture: run and step are explicit parts of the binding. */
function f119Intent(string $runId, string $stepId, string $key = 'SYNTHETIC_SHARED_KEY'): Intent
{
    $run = new Run($runId, context());
    $run->transition('EXECUTANDO');
    return new Intent('SYNTHETIC_INTENT', $run->step($stepId), $key,
        'mock.task.create', 'SYNTHETIC_TASK');
}

return [
    'U19-01 equivalent run and step identifiers preserve intent signature' => function (): void {
        $a = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A');
        $b = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A');
        check($a !== $b && $a->signature() === $b->signature() && $a->key() === $b->key());
    },
    'U19-02 same material and request identity across runs is not same signature' => function (): void {
        $a = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A');
        $b = f119Intent('SYNTHETIC_RUN_B', 'SYNTHETIC_STEP_A');
        check($a->key() === $b->key() && $a->signature() !== $b->signature());
    },
    'U19-03 different step of same run changes signature' => function (): void {
        $a = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A');
        $b = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_B');
        check($a->key() === $b->key() && $a->signature() !== $b->signature());
    },
    'U19-04 approval from earlier run cannot authorize second run' => function (): void {
        $a = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A');
        $b = f119Intent('SYNTHETIC_RUN_B', 'SYNTHETIC_STEP_A');
        $approval = new Approval('SYNTHETIC_APPROVAL_A', $a, 'SYNTHETIC_REVIEWER',
            10, 20, 'APROVADA');
        denied('APPROVAL_REQUIRED', fn() => $approval->assertValid($b, 15));
        $approval->assertValid($a, 15);
    },
    'U19-05 approval from step A cannot authorize step B' => function (): void {
        $a = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A');
        $b = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_B');
        $approval = new Approval('SYNTHETIC_APPROVAL_A', $a, 'SYNTHETIC_REVIEWER',
            10, 20, 'APROVADA');
        denied('APPROVAL_REQUIRED', fn() => $approval->assertValid($b, 15));
    },
    'U19-06 effect gateway rejects reused idempotency key across runs' => function (): void {
        $a = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A');
        $b = f119Intent('SYNTHETIC_RUN_B', 'SYNTHETIC_STEP_A');
        $gateway = new MockCrmEffectGateway('CONFIRMADO');
        $approvalA = new Approval('SYNTHETIC_APPROVAL_A', $a, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $approvalB = new Approval('SYNTHETIC_APPROVAL_B', $b, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        check($gateway->submit($a, $approvalA, 15)->isConfirmed());
        denied('IDEMPOTENCY_CONFLICT', fn() => $gateway->submit($b, $approvalB, 15));
        check($gateway->count() === 1);
    },
    'U19-07 coordinator never resubmits same key from a second run' => function (): void {
        $gateway = new MockSupervisedGateway('CONFIRMADO');
        $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
        $runA = $c->start('SYNTHETIC_RUN_A');
        $runB = $c->start('SYNTHETIC_RUN_B');
        $a = new Intent('SYNTHETIC_INTENT', $c->step($runA, 'SYNTHETIC_STEP_A'),
            'SYNTHETIC_SHARED_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $b = new Intent('SYNTHETIC_INTENT', $c->step($runB, 'SYNTHETIC_STEP_A'),
            'SYNTHETIC_SHARED_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $approvalA = new Approval('SYNTHETIC_APPROVAL_A', $a, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $approvalB = new Approval('SYNTHETIC_APPROVAL_B', $b, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $c->execute($a, $approvalA, 15);
        denied('IDEMPOTENCY_CONFLICT', fn() => $c->execute($b, $approvalB, 15));
        check($gateway->submitCount() === 1);
    },
    'U19-08 coordinator rejects key reuse for a different step' => function (): void {
        $gateway = new MockSupervisedGateway('CONFIRMADO');
        $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
        $run = $c->start('SYNTHETIC_RUN_A');
        $a = new Intent('SYNTHETIC_INTENT', $c->step($run, 'SYNTHETIC_STEP_A'),
            'SYNTHETIC_SHARED_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $b = new Intent('SYNTHETIC_INTENT', $c->step($run, 'SYNTHETIC_STEP_B'),
            'SYNTHETIC_SHARED_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $approvalA = new Approval('SYNTHETIC_APPROVAL_A', $a, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $approvalB = new Approval('SYNTHETIC_APPROVAL_B', $b, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $c->execute($a, $approvalA, 15);
        denied('IDEMPOTENCY_CONFLICT', fn() => $c->execute($b, $approvalB, 15));
        check($gateway->submitCount() === 1);
    },
    'U19-09 independent keys allow independently approved fake steps' => function (): void {
        $gateway = new MockSupervisedGateway('CONFIRMADO');
        $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
        $run = $c->start('SYNTHETIC_RUN_A');
        $a = new Intent('SYNTHETIC_INTENT', $c->step($run, 'SYNTHETIC_STEP_A'),
            'SYNTHETIC_KEY_A', 'mock.task.create', 'SYNTHETIC_TASK');
        $b = new Intent('SYNTHETIC_INTENT', $c->step($run, 'SYNTHETIC_STEP_B'),
            'SYNTHETIC_KEY_B', 'mock.task.create', 'SYNTHETIC_TASK');
        $approvalA = new Approval('SYNTHETIC_APPROVAL_A', $a, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $approvalB = new Approval('SYNTHETIC_APPROVAL_B', $b, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        check($c->execute($a, $approvalA, 15)->isConfirmed());
        check($c->execute($b, $approvalB, 15)->isConfirmed());
        check($gateway->submitCount() === 2);
    },
    'U19-10 approval cannot be replayed with another idempotency key' => function (): void {
        $a = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A', 'SYNTHETIC_KEY_A');
        $b = f119Intent('SYNTHETIC_RUN_A', 'SYNTHETIC_STEP_A', 'SYNTHETIC_KEY_B');
        check($a->signature() !== $b->signature());
        $approval = new Approval('SYNTHETIC_APPROVAL_A', $a,
            'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        denied('APPROVAL_REQUIRED', fn() => $approval->assertValid($b, 15));
    },
    'U19-11 rejected alternate key never causes an extra coordinator submit' => function (): void {
        $gateway = new MockSupervisedGateway('CONFIRMADO');
        $c = new SupervisedCoordinator(context(), $gateway, new MockAiSuggestionProvider());
        $run = $c->start('SYNTHETIC_RUN_A');
        $step = $c->step($run, 'SYNTHETIC_STEP_A');
        $a = new Intent('SYNTHETIC_INTENT', $step, 'SYNTHETIC_KEY_A',
            'mock.task.create', 'SYNTHETIC_TASK');
        $b = new Intent('SYNTHETIC_INTENT', $step, 'SYNTHETIC_KEY_B',
            'mock.task.create', 'SYNTHETIC_TASK');
        $approval = new Approval('SYNTHETIC_APPROVAL_A', $a,
            'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        $c->execute($a, $approval, 15);
        denied('APPROVAL_REQUIRED', fn() => $c->execute($b, $approval, 15));
        check($gateway->submitCount() === 1);
    },

    'U45-01 oversized intent reference is rejected before any approval or submit' => function (): void {
        [, $intent] = fixture();
        $gateway = new MockCrmEffectGateway('CONFIRMADO');
        denied('INVALID_SYNTHETIC_REF', fn() => new Intent(
            'SYNTHETIC_' . str_repeat('I', 119), $intent->step,
            'SYNTHETIC_KEY', 'mock.task.create', 'SYNTHETIC_TASK'));
        check($gateway->count() === 0);
    },
    'U45-02 oversized idempotency key never creates a usable intent' => function (): void {
        [, $intent] = fixture();
        denied('INVALID_SYNTHETIC_REF', fn() => new Intent(
            'SYNTHETIC_F145_INTENT', $intent->step,
            'SYNTHETIC_' . str_repeat('K', 119),
            'mock.task.create', 'SYNTHETIC_TASK'));
    },
    'U45-03 oversized material is rejected before signature allocation' => function (): void {
        [, $intent] = fixture();
        denied('INVALID_SYNTHETIC_REF', fn() => new Intent(
            'SYNTHETIC_F145_INTENT', $intent->step,
            'SYNTHETIC_F145_KEY', 'mock.task.create',
            'SYNTHETIC_' . str_repeat('M', 4087)));
    },
    'U45-04 empty suffix in intent identity key or material is never accepted' => function (): void {
        [, $intent] = fixture();
        foreach ([0, 1, 2] as $index) {
            $parts = [
                'SYNTHETIC_F145_INTENT', 'SYNTHETIC_F145_KEY', 'SYNTHETIC_TASK',
            ];
            $parts[$index] = 'SYNTHETIC_';
            denied('INVALID_SYNTHETIC_REF', fn() => new Intent(
                $parts[0], $intent->step, $parts[1], 'mock.task.create', $parts[2]));
        }
    },
    'U45-05 approval identifiers and reviewers must be nonempty and bounded' => function (): void {
        [, $intent] = fixture();
        foreach ([
            ['SYNTHETIC_', 'SYNTHETIC_REVIEWER'],
            ['SYNTHETIC_' . str_repeat('A', 119), 'SYNTHETIC_REVIEWER'],
            ['SYNTHETIC_APPROVAL', 'SYNTHETIC_'],
            ['SYNTHETIC_APPROVAL', 'SYNTHETIC_' . str_repeat('R', 119)],
        ] as [$id, $approver]) {
            denied('INVALID_SYNTHETIC_REF', fn() => new Approval(
                $id, $intent, $approver, 10, 20, 'APROVADA'));
        }
    },
    'U45-06 exact upper bounds still yield deterministic signatures and approval' => function (): void {
        [, $intent] = fixture();
        $id = 'SYNTHETIC_' . str_repeat('I', 118);
        $key = 'SYNTHETIC_' . str_repeat('K', 118);
        $material = 'SYNTHETIC_' . str_repeat('M', 4086);
        check(strlen($id) === 128 && strlen($key) === 128
            && strlen($material) === 4096);
        $first = new Intent($id, $intent->step, $key, 'mock.task.create', $material);
        $second = new Intent($id, $intent->step, $key, 'mock.task.create', $material);
        $approval = new Approval('SYNTHETIC_' . str_repeat('A', 118),
            $first, 'SYNTHETIC_' . str_repeat('R', 118), 10, 20, 'APROVADA');
        $approval->assertValid($first, 15);
        check($first->signature() === $second->signature()
            && $first->key() === $second->key());
    },
    'U45-07 rejecting large intent has no effect on valid independent submission' => function (): void {
        [$run, $intent] = fixture();
        $gateway = new MockCrmEffectGateway('CONFIRMADO');
        denied('INVALID_SYNTHETIC_REF', fn() => new Intent(
            'SYNTHETIC_' . str_repeat('I', 119), $intent->step,
            'SYNTHETIC_F145_KEY', 'mock.task.create', 'SYNTHETIC_TASK'));
        check($gateway->count() === 0);
        $valid = new Intent('SYNTHETIC_F145_VALID', $intent->step,
            'SYNTHETIC_F145_GOOD_KEY', 'mock.task.create', 'SYNTHETIC_TASK');
        $approval = new Approval('SYNTHETIC_F145_APPROVAL', $valid,
            'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
        check($gateway->submit($valid, $approval, 15)->isConfirmed()
            && $gateway->count() === 1);
    },
];
