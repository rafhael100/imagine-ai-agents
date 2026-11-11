<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\MockReadOnlyRunQueue;
use ImagineAI\MockPilot\MockQueuedBatchRunner;

function f183QueuedState(string $state): array
{
    return ['contract' => 'SYNTHETIC_QUEUED_PILOT_V1',
        'state' => $state, 'summary' => null,
        'real_authorization' => false, 'external_calls' => 0,
        'write_calls' => 0];
}

return [
    'U83-01 queue distinguishes active claims from expired uncertain claims' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        check(!$q->needsReview(context()) && !$q->hasActiveReservation(context()));
        $q->enqueue(context(), 'SYNTHETIC_U83_QUEUE_A', 'SYNTHETIC_U83_REQ_A', 10);
        $q->claim(context(), 11, 3);
        check(!$q->needsReview(context()) && $q->hasActiveReservation(context()));
        check($q->claim(context(), 14, 3) === null);
        check($q->needsReview(context()) && !$q->hasActiveReservation(context())
            && $q->counts()['INDETERMINADO'] === 1
            && count($q->events()) === 3);
    },
    'U83-02 active lease gives IN_PROGRESS without another CRM read' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_02', 'SYNTHETIC_U83_REQ_02', 10);
        $q->claim($ctx, 11, 5);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 12, 5)
            === f183QueuedState('IN_PROGRESS'));
        check($q->counts()['RESERVADA'] === 1 && !$q->needsReview($ctx)
            && $audit->count() === 0 && $coord->events() === []
            && $counts->snapshot()['attempts'] === 0 && $mut->submitCount() === 0);
    },
    'U83-03 exact lease boundary gives REVIEW_REQUIRED not EMPTY' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_03', 'SYNTHETIC_U83_REQ_03', 10);
        $q->claim($ctx, 11, 3);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 14, 5)
            === f183QueuedState('REVIEW_REQUIRED'));
        check($q->counts()['INDETERMINADO'] === 1 && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0 && $mut->submitCount() === 0);
    },
    'U83-04 batch with active claim does not count a phantom processed job' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_04', 'SYNTHETIC_U83_REQ_04', 10);
        $q->claim($ctx, 11, 5);
        $out = (new MockQueuedBatchRunner($ctx, $runner))->drain($ctx,
            [['envelope' => $env, 'owner' => $owner]], ['ref'], 12, 5);
        check($out['state'] === 'IN_PROGRESS'
            && $out['processed'] === 0 && $out['succeeded'] === 0
            && $out['denied'] === 0 && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0);
    },
    'U83-05 batch with expired only claim reports review with zero new reads' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_05', 'SYNTHETIC_U83_REQ_05', 10);
        $q->claim($ctx, 11, 2);
        $out = (new MockQueuedBatchRunner($ctx, $runner))->drain($ctx,
            [['envelope' => $env, 'owner' => $owner]], ['ref'], 13, 5);
        check($out['state'] === 'REVIEW_REQUIRED'
            && $out['processed'] === 0 && $out['succeeded'] === 0
            && $out['denied'] === 0
            && $q->counts()['INDETERMINADO'] === 1
            && $coord->events() === [] && $audit->count() === 0);
    },
    'U83-06 partial batch success preserves outstanding review without fake count' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_06A', 'SYNTHETIC_U83_REQ_06A', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_06B', 'SYNTHETIC_U83_REQ_06B', 10);
        $q->claim($ctx, 11, 2);
        [$env2, $owner2] = f130Proof($ctx, 'U83_06B');
        $out = (new MockQueuedBatchRunner($ctx, $runner))->drain($ctx, [
            ['envelope' => $env2, 'owner' => $owner2],
            ['envelope' => $env, 'owner' => $owner],
        ], ['ref'], 15, 5);
        check($out['state'] === 'REVIEW_REQUIRED'
            && $out['processed'] === 1 && $out['succeeded'] === 1
            && $out['denied'] === 0
            && $q->counts()['INDETERMINADO'] === 1
            && $q->counts()['CONCLUIDA'] === 1
            && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $mut->submitCount() === 0);
    },
    'U83-07 canceled final job is EMPTY not review or in progress' => function (): void {
        [$runner, $q, , $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_07', 'SYNTHETIC_U83_REQ_07', 10);
        $q->cancel($ctx, 'SYNTHETIC_U83_RUN_07', 11);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 12, 5)
            === f183QueuedState('EMPTY'));
        check(!$q->needsReview($ctx) && !$q->hasActiveReservation($ctx)
            && $counts->snapshot()['attempts'] === 0 && $audit->count() === 0);
    },
    'U83-08 known denied final job remains terminal and next poll is EMPTY' => function (): void {
        [$runner, $q, , $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_08', 'SYNTHETIC_U83_REQ_08', 10);
        check($runner->runNext($ctx, null, null, ['ref'], 15, 5)
            === f183QueuedState('DENIED_OR_NOT_FOUND'));
        check($runner->runNext($ctx, $env, $owner, ['ref'], 16, 5)
            === f183QueuedState('EMPTY'));
        check($q->counts()['FALHOU'] === 1 && !$q->needsReview($ctx)
            && $coord->events() === []);
    },
    'U83-09 status outputs expose neither request nor lease identifiers' => function (): void {
        [$runner, $q, , , , , $env, $owner, $ctx] = f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_PRIVATE_RUN',
            'SYNTHETIC_U83_PRIVATE_REQ', 10);
        $q->claim($ctx, 11, 2);
        $states = [
            $runner->runNext($ctx, $env, $owner, ['ref'], 12, 5),
            $runner->runNext($ctx, $env, $owner, ['ref'], 13, 5),
        ];
        check($states[0] === f183QueuedState('IN_PROGRESS')
            && $states[1] === f183QueuedState('REVIEW_REQUIRED'));
        $visible = json_encode($states, JSON_THROW_ON_ERROR);
        foreach ([$ctx->tenant, $ctx->resource, $ctx->principal, 'PRIVATE_RUN',
            'PRIVATE_REQ', 'claim_ref', 'nonce'] as $private) {
            check(!str_contains($visible, $private));
        }
    },
    'U83-10 review state cannot be queried by another tenant context' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U83_RUN_10', 'SYNTHETIC_U83_REQ_10', 10);
        denied('CONTEXT_DENIED', fn () => $q->needsReview(context('SYNTHETIC_B')));
        denied('CONTEXT_DENIED', fn () =>
            $q->hasActiveReservation(context('SYNTHETIC_B')));
        check(!$q->needsReview(context()) && !$q->hasActiveReservation(context()));
    },
    'U83-11 repeated active polls neither allocate new claims nor read' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_11', 'SYNTHETIC_U83_REQ_11', 10);
        $q->claim($ctx, 11, 5);
        foreach ([12, 13, 15] as $now) {
            check($runner->runNext($ctx, $env, $owner, ['ref'], $now, 5)
                === f183QueuedState('IN_PROGRESS'));
        }
        check($q->counts()['RESERVADA'] === 1
            && count($q->events()) === 2
            && $coord->events() === [] && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0 && $mut->submitCount() === 0);
    },
    'U83-12 repeated review polls preserve uncertainty without retries' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U83_RUN_12', 'SYNTHETIC_U83_REQ_12', 10);
        $q->claim($ctx, 11, 2);
        foreach ([13, 14, 15] as $now) {
            check($runner->runNext($ctx, $env, $owner, ['ref'], $now, 5)
                === f183QueuedState('REVIEW_REQUIRED'));
        }
        check($q->counts()['INDETERMINADO'] === 1
            && count($q->events()) === 3
            && $coord->events() === [] && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0 && $mut->submitCount() === 0);
    },
];
