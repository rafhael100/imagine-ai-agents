<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\{MockQueueTriage, MockReadOnlyRunQueue, MockUncertaintyReviewBoard};

function f200TriageFixture(): array
{
    $ctx = context();
    $queue = new MockReadOnlyRunQueue($ctx);
    $board = new MockUncertaintyReviewBoard($ctx, $queue);
    return [$queue, $board, new MockQueueTriage($ctx, $board), $ctx];
}

return [
    'U100-01 empty queue is quiet but never authorized' => function (): void {
        [$q, $b, $triage, $ctx] = f200TriageFixture();
        $out = $triage->assess($ctx);
        check($out['contract'] === 'SYNTHETIC_QUEUE_TRIAGE_V1'
            && $out['state'] === 'QUIET'
            && $out['next_step'] === 'NO_LOCAL_WORK'
            && $out['operational_decision'] === 'NO_GO'
            && $out['real_authorization'] === false
            && $out['external_calls'] === 0 && $out['write_calls'] === 0
            && array_sum($out['queue']) === 0 && $q->events() === []);
    },
    'U100-02 pending job yields observation only' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_A', 'SYNTHETIC_U100_REQ_A', 10);
        $out = $triage->assess($ctx);
        check($out['state'] === 'PENDING'
            && $out['next_step'] === 'OBSERVE_PENDING'
            && $out['queue']['pending'] === 1
            && count($q->events()) === 1);
    },
    'U100-03 active lease precedes pending work' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        foreach (['A', 'B'] as $id) {
            $q->enqueue($ctx, 'SYNTHETIC_U100_' . $id,
                'SYNTHETIC_U100_REQ_' . $id, 10);
        }
        $q->claim($ctx, 11, 20);
        $out = $triage->assess($ctx);
        check($out['state'] === 'LEASE_IN_PROGRESS'
            && $out['next_step'] === 'OBSERVE_LEASE'
            && $out['queue']['reserved'] === 1
            && $out['queue']['pending'] === 1);
    },
    'U100-04 expired lease requires review and never retry' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_C', 'SYNTHETIC_U100_REQ_C', 10);
        $q->claim($ctx, 11, 2);
        check($q->claim($ctx, 13, 5) === null);
        $out = $triage->assess($ctx);
        check($out['state'] === 'REVIEW_REQUIRED'
            && $out['next_step'] === 'INSPECT_UNCERTAINTY'
            && $out['review']['awaiting_first'] === 1
            && $out['queue']['indeterminate'] === 1);
    },
    'U100-05 paused queue takes priority over pending jobs' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_D', 'SYNTHETIC_U100_REQ_D', 10);
        $q->pauseAdmission($ctx, 'SYNTHETIC_U100_PAUSE_D');
        $out = $triage->assess($ctx);
        check($out['state'] === 'PAUSED'
            && $out['next_step'] === 'INSPECT_PAUSE'
            && $out['queue']['pending'] === 1);
    },
    'U100-06 paused queue with expired lease remains paused and uncertain' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_E', 'SYNTHETIC_U100_REQ_E', 10);
        $q->claim($ctx, 11, 2);
        $q->pauseAdmission($ctx, 'SYNTHETIC_U100_PAUSE_E');
        $q->claim($ctx, 13, 5);
        $out = $triage->assess($ctx);
        check($out['state'] === 'PAUSED'
            && $out['queue']['indeterminate'] === 1
            && $out['review']['awaiting_first'] === 1);
    },
    'U100-07 two matching reviews do not clear uncertainty' => function (): void {
        [$q, $board, $triage, $ctx] = f200TriageFixture();
        $id = 'SYNTHETIC_U100_F';
        $q->enqueue($ctx, $id, 'SYNTHETIC_U100_REQ_F', 10);
        $q->claim($ctx, 11, 2);
        $q->claim($ctx, 13, 5);
        foreach (['A', 'B'] as $who) {
            $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_' . $who,
                'SYNTHETIC_TIMEOUT_OBSERVED');
        }
        $out = $triage->assess($ctx);
        check($out['state'] === 'REVIEW_REQUIRED'
            && $out['review']['reviewed_unresolved'] === 1
            && $out['queue']['indeterminate'] === 1
            && $out['real_authorization'] === false);
    },
    'U100-08 conflicting reviews are never approved' => function (): void {
        [$q, $board, $triage, $ctx] = f200TriageFixture();
        $id = 'SYNTHETIC_U100_G';
        $q->enqueue($ctx, $id, 'SYNTHETIC_U100_REQ_G', 10);
        $q->claim($ctx, 11, 2);
        $q->claim($ctx, 13, 5);
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_B',
            'SYNTHETIC_RESULT_UNKNOWN');
        $out = $triage->assess($ctx);
        check($out['state'] === 'REVIEW_REQUIRED'
            && $out['review']['conflict'] === 1
            && $out['operational_decision'] === 'NO_GO');
    },
    'U100-09 completed work leaves no synthetic work pending' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $id = 'SYNTHETIC_U100_H';
        $q->enqueue($ctx, $id, 'SYNTHETIC_U100_REQ_H', 10);
        $lease = $q->claim($ctx, 11, 10);
        $q->finish($ctx, $id, $lease['claim_ref'], 'CONCLUIDA', 12);
        $out = $triage->assess($ctx);
        check($out['state'] === 'QUIET'
            && $out['queue']['completed'] === 1
            && $out['real_authorization'] === false);
    },
    'U100-10 cancelled work never appears as pending' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $id = 'SYNTHETIC_U100_I';
        $q->enqueue($ctx, $id, 'SYNTHETIC_U100_REQ_I', 10);
        $q->cancel($ctx, $id, 11);
        $out = $triage->assess($ctx);
        check($out['state'] === 'QUIET'
            && $out['queue']['cancelled'] === 1
            && $out['queue']['pending'] === 0);
    },
    'U100-11 foreign tenant cannot inspect triage' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        denied('CONTEXT_DENIED', fn () => $triage->assess(context('SYNTHETIC_B')));
        check($q->events() === [] && $triage->assess($ctx)['state'] === 'QUIET');
    },
    'U100-12 board mismatched to queue tenant cannot inspect state' => function (): void {
        $ctx = context();
        $q = new MockReadOnlyRunQueue(context('SYNTHETIC_B'));
        $board = new MockUncertaintyReviewBoard($ctx, $q);
        $triage = new MockQueueTriage($ctx, $board);
        denied('CONTEXT_DENIED', fn () => $triage->assess($ctx));
    },
    'U100-13 assessment is immutable and contains no raw identifiers' => function (): void {
        [$q, $board, $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_J', 'SYNTHETIC_U100_REQ_J', 10);
        $before = $q->events();
        $out = $triage->assess($ctx);
        check($out === $triage->assess($ctx)
            && $q->events() === $before && $board->events() === []);
        $encoded = json_encode($out, JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC_U100_J', 'SYNTHETIC_U100_REQ_J',
            $ctx->tenant, $ctx->resource, 'claim_ref', 'run_ref',
            'reviewer_digest', 'nonce'] as $secret) {
            check(!str_contains($encoded, $secret));
        }
    },
    'U100-14 resumed queue does not dismiss uncertainty' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_K', 'SYNTHETIC_U100_REQ_K', 10);
        $q->claim($ctx, 11, 2);
        $q->pauseAdmission($ctx, 'SYNTHETIC_U100_PAUSE_K');
        $q->claim($ctx, 13, 5);
        check($triage->assess($ctx)['state'] === 'PAUSED');
        $q->resumeAdmission($ctx, 'SYNTHETIC_U100_RESUME_K');
        check($triage->assess($ctx)['state'] === 'REVIEW_REQUIRED'
            && $q->counts()['INDETERMINADO'] === 1);
    },
    'U100-15 state precedence favors uncertainty over reservations' => function (): void {
        [$q, , $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_L', 'SYNTHETIC_U100_REQ_L', 10);
        $q->enqueue($ctx, 'SYNTHETIC_U100_M', 'SYNTHETIC_U100_REQ_M', 10);
        $q->claim($ctx, 11, 2);
        $q->claim($ctx, 13, 5);
        $out = $triage->assess($ctx);
        check($out['queue']['reserved'] === 1
            && $out['queue']['indeterminate'] === 1
            && $out['state'] === 'REVIEW_REQUIRED');
    },
    'U100-16 arbitrary repeated assessments never mutate state or audit' => function (): void {
        [$q, $board, $triage, $ctx] = f200TriageFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U100_N', 'SYNTHETIC_U100_REQ_N', 10);
        $events = $q->events();
        for ($i = 0; $i < 100; ++$i) {
            $v = $triage->assess($ctx);
            check($v['state'] === 'PENDING'
                && $v['external_calls'] === 0 && $v['write_calls'] === 0);
        }
        check($q->events() === $events && $board->events() === []);
    },
];
