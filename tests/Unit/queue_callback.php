<?php
declare(strict_types=1);

use ImagineAI\Domain\MockError;
use ImagineAI\MockOrchestration\MockReadOnlyRunQueue;
use ImagineAI\MockPilot\{MockReadOnlyPilot, MockQueuedReadOnlyPilot, MockQueuedBatchRunner};

/** Synthetic provider callback fixture; NO authentication or real dispatch. */
function f182QueuedFixture(\Closure $duringProvider): array
{
    [$pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx, $policy,
        $provider] = f167Fixture($duringProvider);
    $queue = new MockReadOnlyRunQueue($ctx);
    $runner = new MockQueuedReadOnlyPilot($ctx, $queue, $pilot);
    return [$runner, $queue, $pilot, $coord, $mut, $counts, $audit,
        $env, $owner, $ctx, $provider];
}

function f182Denial(): array
{
    return ['contract' => 'SYNTHETIC_QUEUED_PILOT_V1',
        'state' => 'DENIED_OR_NOT_FOUND', 'summary' => null,
        'real_authorization' => false, 'external_calls' => 0,
        'write_calls' => 0];
}

return [
    'U82-01 swallowed same-runner callback cannot claim next queued job' => function (): void {
        $runner = null; $nested = null;
        [$runner, $q, , $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$runner, &$nested): void {
                $nested = $runner->runNext(context(), null, null, ['ref'], 15, 5);
            });
        $q->enqueue($ctx, 'SYNTHETIC_U82_RUN_A', 'SYNTHETIC_U82_REQ_A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U82_RUN_B', 'SYNTHETIC_U82_REQ_B', 10);
        $outer = $runner->runNext($ctx, $env, $owner, ['ref'], 15, 5);
        check($nested === f182Denial() && $outer === f182Denial());
        check($q->counts()['FALHOU'] === 1 && $q->counts()['PENDENTE'] === 1);
        check($counts->snapshot()['attempts'] === 1 && $audit->count() === 1);
        check($mut->submitCount() === 0 && count($coord->events()) === 3);
    },
    'U82-02 recursive batch denies before reserving its first job' => function (): void {
        $batch = null; $nested = null;
        [$runner, $q, , $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$batch, &$nested): void {
                $nested = $batch->drain(context(),
                    [['envelope' => null, 'owner' => null]], ['ref'], 15, 5);
            });
        $batch = new MockQueuedBatchRunner($ctx, $runner);
        $q->enqueue($ctx, 'SYNTHETIC_U82_BATCH_A', 'SYNTHETIC_U82_REQ_A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U82_BATCH_B', 'SYNTHETIC_U82_REQ_B', 10);
        $out = $runner->runNext($ctx, $env, $owner, ['ref'], 15, 5);
        check($out === f182Denial()
            && $nested['state'] === 'DENIED_OR_NOT_FOUND'
            && $nested['processed'] === 0);
        check($q->counts()['FALHOU'] === 1 && $q->counts()['PENDENTE'] === 1
            && $counts->snapshot()['attempts'] === 1
            && $mut->submitCount() === 0);
    },
    'U82-03 second queued runner with shared coordinator cannot bypass guard' => function (): void {
        $other = null; $otherQ = null; $nested = null;
        [$runner, $q, $pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$other, &$nested): void {
                $nested = $other->runNext(context(), null, null, ['ref'], 15, 5);
            });
        $otherQ = new MockReadOnlyRunQueue($ctx);
        $other = new MockQueuedReadOnlyPilot($ctx, $otherQ, $pilot);
        $q->enqueue($ctx, 'SYNTHETIC_U82_MAIN_A', 'SYNTHETIC_U82_REQ_A', 10);
        $otherQ->enqueue($ctx, 'SYNTHETIC_U82_OTHER', 'SYNTHETIC_U82_REQ_B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5) === f182Denial());
        check($nested === f182Denial() && $otherQ->counts()['PENDENTE'] === 1
            && $q->counts()['FALHOU'] === 1 && $mut->submitCount() === 0);
        check($counts->snapshot()['attempts'] === 1 && $audit->count() === 1);
    },
    'U82-04 direct admission preflight taints swallowed nested callback' => function (): void {
        $runner = null; $blocked = false;
        [$runner, $q, , $coord, $mut, , , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$runner, &$blocked): void {
                try { $runner->assertQueueAdmissionReady(); }
                catch (MockError $e) {
                    $blocked = $e->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U82_PREFLIGHT', 'SYNTHETIC_U82_REQ_A', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($blocked && $q->counts()['FALHOU'] === 1
            && $mut->submitCount() === 0);
    },
    'U82-05 malformed nested fields fail before queue admission and taint outer' => function (): void {
        $runner = null; $nested = null;
        [$runner, $q, , , $mut, $counts, , $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$runner, &$nested): void {
                $nested = $runner->runNext(context(), null, null,
                    ['email', str_repeat('X', 1024 * 1024)], 15, 5);
            });
        $q->enqueue($ctx, 'SYNTHETIC_U82_MAL_A', 'SYNTHETIC_U82_REQ_A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U82_MAL_B', 'SYNTHETIC_U82_REQ_B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($nested === f182Denial() && $q->counts()['PENDENTE'] === 1
            && $counts->snapshot()['attempts'] === 1
            && $mut->submitCount() === 0);
    },
    'U82-06 repeated suppressed reentries never consume additional jobs' => function (): void {
        $runner = null; $calls = 0;
        [$runner, $q, , $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$runner, &$calls): void {
                for ($i = 0; $i < 3; ++$i) {
                    $denial = $runner->runNext(context(), null, null, ['ref'], 15, 5);
                    if ($denial['state'] === 'DENIED_OR_NOT_FOUND') ++$calls;
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U82_REPEAT_A', 'SYNTHETIC_U82_REQ_A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U82_REPEAT_B', 'SYNTHETIC_U82_REQ_B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($calls === 3 && $q->counts()['PENDENTE'] === 1
            && $q->counts()['FALHOU'] === 1 && $audit->count() === 1
            && $counts->snapshot()['attempts'] === 1
            && $mut->submitCount() === 0);
    },
    'U82-07 provider exception after blocked recursion leaves queue safe' => function (): void {
        $runner = null;
        [$runner, $q, , $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$runner): void {
                $runner->runNext(context(), null, null, ['ref'], 15, 5);
                throw new \RuntimeException('SYNTHETIC_PRIVATE_PROVIDER_ERROR');
            });
        $q->enqueue($ctx, 'SYNTHETIC_U82_THROW_A', 'SYNTHETIC_U82_REQ_A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U82_THROW_B', 'SYNTHETIC_U82_REQ_B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($q->counts()['PENDENTE'] === 1 && $q->counts()['FALHOU'] === 1
            && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $mut->submitCount() === 0);
    },
    'U82-08 fresh valid independent Run succeeds after blocked recursion' => function (): void {
        $runner = null; $callbacks = 0;
        [$runner, $q, , $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$runner, &$callbacks): void {
                if (++$callbacks === 1) {
                    $runner->runNext(context(), null, null, ['ref'], 15, 5);
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U82_RECOVER_A', 'SYNTHETIC_U82_REQ_A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U82_RECOVER_B', 'SYNTHETIC_U82_REQ_B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        [$env2, $owner2] = f130Proof($ctx, 'U82_RECOVER_B');
        check($runner->runNext($ctx, $env2, $owner2, ['ref'], 16, 5)['state']
            === 'SUGGESTION_SYNTHETIC_ONLY');
        check($callbacks === 2 && $q->counts()['FALHOU'] === 1
            && $q->counts()['CONCLUIDA'] === 1 && $audit->count() === 2
            && $counts->snapshot()['attempts'] === 2
            && $mut->submitCount() === 0);
    },
    'U82-09 clean queue admission allows normal offline batch processing' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $batch = new MockQueuedBatchRunner($ctx, $runner);
        $runner->assertQueueAdmissionReady();
        $q->enqueue($ctx, 'SYNTHETIC_U82_CLEAN', 'SYNTHETIC_U82_REQ_A', 10);
        $out = $batch->drain($ctx, [['envelope' => $env, 'owner' => $owner]],
            ['ref'], 15, 5);
        check($out['state'] === 'BATCH_SYNTHETIC_ONLY'
            && $out['processed'] === 1 && $out['succeeded'] === 1
            && $q->counts()['CONCLUIDA'] === 1
            && $mut->submitCount() === 0);
    },
    'U82-10 foreign context nested into a batch still taints outer callback' => function (): void {
        $batch = null; $nested = null;
        [$runner, $q, , $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$batch, &$nested): void {
                $nested = $batch->drain(context('SYNTHETIC_B'),
                    [['envelope' => null, 'owner' => null]], ['ref'], 15, 5);
            });
        $batch = new MockQueuedBatchRunner($ctx, $runner);
        $q->enqueue($ctx, 'SYNTHETIC_U82_FOREIGN_A', 'SYNTHETIC_U82_REQ_A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U82_FOREIGN_B', 'SYNTHETIC_U82_REQ_B', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($nested['processed'] === 0 && $nested['state'] === 'DENIED_OR_NOT_FOUND'
            && $q->counts()['PENDENTE'] === 1 && $q->counts()['FALHOU'] === 1
            && $counts->snapshot()['attempts'] === 1
            && $mut->submitCount() === 0);
    },
];
