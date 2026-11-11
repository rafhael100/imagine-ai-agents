<?php
declare(strict_types=1);

use ImagineAI\Domain\MockError;
use ImagineAI\MockOrchestration\{MockReadOnlyRunQueue, SupervisedCoordinator};
use ImagineAI\Mocks\{MockSupervisedGateway, MockAiSuggestionProvider};
use ImagineAI\MockPilot\MockQueuedReadOnlyPilot;

/**
 * U85 — direct queue mutators are forbidden while their bound coordinator
 * runs a synthetic suggestion provider, including swallowed exceptions.
 */
return [
    'U85-01 direct claim inside callback cannot reserve second pending job' => function (): void {
        $q = null; $blocked = false;
        [$runner, $q, , $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$blocked): void {
                try { $q->claim(context(), 15, 5); }
                catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_01A', 'SYNTHETIC_U85_REQ_01A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_01B', 'SYNTHETIC_U85_REQ_01B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5) === f182Denial());
        check($blocked && $q->counts()['FALHOU'] === 1
            && $q->counts()['PENDENTE'] === 1
            && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $mut->submitCount() === 0);
    },
    'U85-02 direct enqueue inside provider cannot insert a hidden job' => function (): void {
        $q = null; $blocked = false;
        [$runner, $q, , , $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$blocked): void {
                try {
                    $q->enqueue(context(), 'SYNTHETIC_U85_FORGED',
                        'SYNTHETIC_U85_REQ_FORGED', 15);
                } catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_02', 'SYNTHETIC_U85_REQ_02', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5) === f182Denial());
        check($blocked && $q->status($ctx, 'SYNTHETIC_U85_FORGED')['state']
                === 'DENIED_OR_NOT_FOUND'
            && $q->counts()['FALHOU'] === 1
            && count($q->events()) === 3 && $mut->submitCount() === 0);
    },
    'U85-03 provider cannot directly cancel a separate pending job' => function (): void {
        $q = null; $blocked = false;
        [$runner, $q, , , , , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$blocked): void {
                try { $q->cancel(context(), 'SYNTHETIC_U85_RUN_03B', 15); }
                catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_03A', 'SYNTHETIC_U85_REQ_03A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_03B', 'SYNTHETIC_U85_REQ_03B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5) === f182Denial());
        check($blocked && $q->counts()['CANCELADA'] === 0
            && $q->counts()['PENDENTE'] === 1 && $q->counts()['FALHOU'] === 1);
    },
    'U85-04 provider cannot finish a different leased job directly' => function (): void {
        $q = null; $lease = null; $blocked = false;
        [$runner, $q, , , $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$lease, &$blocked): void {
                try {
                    $q->finish(context(), $lease['run_ref'], $lease['claim_ref'],
                        'CONCLUIDA', 15);
                } catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_04B', 'SYNTHETIC_U85_REQ_04B', 10);
        $lease = $q->claim($ctx, 11, 10);
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_04A', 'SYNTHETIC_U85_REQ_04A', 11);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5) === f182Denial());
        check($blocked && $q->counts()['RESERVADA'] === 1
            && $q->counts()['CONCLUIDA'] === 0 && $q->counts()['FALHOU'] === 1
            && $mut->submitCount() === 0);
    },
    'U85-05 foreign tenant direct mutation also taints outer suggestion' => function (): void {
        $q = null; $blocked = false;
        [$runner, $q, , , , , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$blocked): void {
                try {
                    $q->enqueue(context('SYNTHETIC_B'), 'SYNTHETIC_U85_FOREIGN',
                        'SYNTHETIC_U85_REQ_FOREIGN', 15);
                } catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_05', 'SYNTHETIC_U85_REQ_05', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5) === f182Denial());
        check($blocked && $q->counts()['FALHOU'] === 1
            && $q->status($ctx, 'SYNTHETIC_U85_FOREIGN')['state']
                === 'DENIED_OR_NOT_FOUND');
    },
    'U85-06 normal queue mutation is restored after failed provider callback' => function (): void {
        $q = null; $calls = 0;
        [$runner, $q, , , $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$calls): void {
                if (++$calls === 1) {
                    try { $q->claim(context(), 15, 5); }
                    catch (MockError) { /* deliberately swallowed */ }
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_06A', 'SYNTHETIC_U85_REQ_06A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_06B', 'SYNTHETIC_U85_REQ_06B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5) === f182Denial());
        [$env2, $owner2] = f130Proof($ctx, 'U85_RECOVERY');
        check($runner->runNext($ctx, $env2, $owner2, ['ref'], 16, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($calls === 2 && $q->counts()['FALHOU'] === 1
            && $q->counts()['CONCLUIDA'] === 1
            && $counts->snapshot()['attempts'] === 2
            && $audit->count() === 2 && $mut->submitCount() === 0);
    },
    'U85-07 same pilot can rebind same queue without changing identity' => function (): void {
        [$runner, $q, $pilot, , $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function (): void {});
        $pilot->bindQueueAdmissionGuard($q);
        $pilot->bindQueueAdmissionGuard($q);
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_07', 'SYNTHETIC_U85_REQ_07', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($q->counts()['CONCLUIDA'] === 1 && $mut->submitCount() === 0);
    },
    'U85-08 second coordinator cannot replace original bound queue owner' => function (): void {
        [$runner, $q, $pilot, , $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function (): void {});
        $other = new SupervisedCoordinator($ctx, new MockSupervisedGateway(),
            new MockAiSuggestionProvider());
        denied('QUEUE_COORDINATOR_CONFLICT',
            fn () => $q->bindAdmissionCoordinator($other));
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_08', 'SYNTHETIC_U85_REQ_08', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($q->counts()['CONCLUIDA'] === 1 && $mut->submitCount() === 0);
    },
    'U85-09 swallowed rebind attempt during callback poisons original result' => function (): void {
        $q = null; $pilot = null; $blocked = false;
        [$runner, $q, $pilot, , $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$pilot, &$q, &$blocked): void {
                try { $pilot->bindQueueAdmissionGuard($q); }
                catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_09', 'SYNTHETIC_U85_REQ_09', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($blocked && $q->counts()['FALHOU'] === 1
            && $mut->submitCount() === 0);
    },
    'U85-10 direct mutators cannot slip through by invalid or huge arguments' => function (): void {
        $q = null; $blocked = 0;
        [$runner, $q, , , $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$blocked): void {
                foreach ([
                    fn () => $q->enqueue(context(), str_repeat('X', 1024 * 1024),
                        'BAD', 15),
                    fn () => $q->cancel(context(), 'WRONG', 15),
                    fn () => $q->claim(context(), 15, 9999),
                ] as $attack) {
                    try { $attack(); }
                    catch (MockError $e) {
                        if ($e->getMessage() === 'PROVIDER_CALLBACK_DENIED') ++$blocked;
                    }
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_10A', 'SYNTHETIC_U85_REQ_10A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U85_RUN_10B', 'SYNTHETIC_U85_REQ_10B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($blocked === 3 && $q->counts()['PENDENTE'] === 1
            && $q->counts()['FALHOU'] === 1
            && $mut->submitCount() === 0);
    },
    'U85-11 a separate runner sharing coordinator cannot bypass direct guards' => function (): void {
        $other = null; $q = null; $q2 = null; $blocked = false;
        [$runner, $q, $pilot, , $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q2, &$blocked): void {
                try { $q2->claim(context(), 15, 5); }
                catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q2 = new MockReadOnlyRunQueue($ctx);
        $other = new MockQueuedReadOnlyPilot($ctx, $q2, $pilot);
        $q->enqueue($ctx, 'SYNTHETIC_U85_MAIN_A', 'SYNTHETIC_U85_REQ_11A', 10);
        $q2->enqueue($ctx, 'SYNTHETIC_U85_OTHER', 'SYNTHETIC_U85_REQ_11B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($blocked && $q2->counts()['PENDENTE'] === 1
            && $q->counts()['FALHOU'] === 1 && $mut->submitCount() === 0);
    },
    'U85-12 standalone unbound laboratory queue still supports basic FIFO' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U85_STANDALONE',
            'SYNTHETIC_U85_REQ_STANDALONE', 10);
        $claim = $q->claim(context(), 11, 5);
        check($q->finish(context(), $claim['run_ref'], $claim['claim_ref'],
            'CONCLUIDA', 12)['state'] === 'CONCLUIDA');
        check($q->counts()['CONCLUIDA'] === 1 && count($q->events()) === 3);
    },
];
