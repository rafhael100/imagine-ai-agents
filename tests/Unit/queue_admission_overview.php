<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\{MockReadOnlyRunQueue, MockUncertaintyReviewBoard};

return [
    'U93-01 supervision includes closed OPEN admission state on empty queue' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $v = (new MockUncertaintyReviewBoard(context(), $q))->overview(context());
        check($v['admission'] === ['state' => 'OPEN']
            && array_sum($v['queue']) === 0
            && array_sum($v['review']) === 0
            && $v['operational_decision'] === 'NO_GO'
            && $v['real_authorization'] === false);
    },
    'U93-02 pause appears in read-only overview without claiming pending job' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U93_PENDING', 'SYNTHETIC_U93_REQ_PENDING', 10);
        $q->pauseAdmission(context(), 'SYNTHETIC_U93_PAUSE');
        $board = new MockUncertaintyReviewBoard(context(), $q);
        $before = $q->events();
        $v = $board->overview(context());
        check($v['admission'] === ['state' => 'PAUSED']
            && $v['queue']['pending'] === 1
            && $v['queue']['reserved'] === 0
            && $v['queue']['indeterminate'] === 0
            && $q->events() === $before);
    },
    'U93-03 resume appears OPEN without silently completing queued reads' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U93_REOPEN', 'SYNTHETIC_U93_REQ_REOPEN', 10);
        $board = new MockUncertaintyReviewBoard(context(), $q);
        $q->pauseAdmission(context(), 'SYNTHETIC_U93_PAUSE_R');
        $before = $board->overview(context());
        $q->resumeAdmission(context(), 'SYNTHETIC_U93_RESUME_R');
        $after = $board->overview(context());
        check($before['admission']['state'] === 'PAUSED'
            && $after['admission']['state'] === 'OPEN'
            && $after['queue']['pending'] === 1
            && $after['queue']['completed'] === 0
            && $q->counts()['PENDENTE'] === 1);
    },
    'U93-04 forged tenant cannot inspect paused admission status' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->pauseAdmission(context(), 'SYNTHETIC_U93_PAUSE_FOREIGN');
        $board = new MockUncertaintyReviewBoard(context(), $q);
        denied('CONTEXT_DENIED', fn () => $board->overview(context('SYNTHETIC_B')));
        check($board->overview(context())['admission']['state'] === 'PAUSED');
    },
    'U93-05 indeterminate leases stay visible while admission paused' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U93_UNKNOWN', 'SYNTHETIC_U93_REQ_UNKNOWN', 10);
        $q->enqueue(context(), 'SYNTHETIC_U93_NEXT', 'SYNTHETIC_U93_REQ_NEXT', 10);
        $q->claim(context(), 11, 2);
        $q->pauseAdmission(context(), 'SYNTHETIC_U93_PAUSE_UNCERTAIN');
        check($q->claim(context(), 13, 5) === null);
        $v = (new MockUncertaintyReviewBoard(context(), $q))->overview(context());
        check($v['admission'] === ['state' => 'PAUSED']
            && $v['queue']['indeterminate'] === 1
            && $v['review']['awaiting_first'] === 1
            && $v['queue']['pending'] === 1
            && $v['queue']['reserved'] === 0
            && $v['real_authorization'] === false);
    },
    'U93-06 two reviewer observations stay unresolved during suspension' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        $q->pauseAdmission($ctx, 'SYNTHETIC_U93_PAUSE_VOTES');
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_B',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $v = $board->overview($ctx);
        check($v['admission']['state'] === 'PAUSED'
            && $v['queue']['indeterminate'] === 1
            && $v['review']['reviewed_unresolved'] === 1
            && $v['review']['awaiting_second'] === 0
            && $q->counts()['CONCLUIDA'] === 0);
    },
    'U93-07 overview does not expose decision refs or reviewers in either state' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        $q->pauseAdmission($ctx, 'SYNTHETIC_U93_PRIVATE_PAUSE');
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_RESULT_UNKNOWN');
        $v = $board->overview($ctx);
        $json = json_encode($v, JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC_U93_PRIVATE_PAUSE', $id, $ctx->tenant,
            'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_RESULT_UNKNOWN',
            'claim_ref', 'request_ref', 'nonce', 'reviewer_digest'] as $marker) {
            check(!str_contains($json, $marker));
        }
        check($v['admission'] === ['state' => 'PAUSED']
            && $v['external_calls'] === 0 && $v['write_calls'] === 0);
    },
    'U93-08 board attached to bound pilot queue reports pause with no CRM read' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U93_PILOT', 'SYNTHETIC_U93_REQ_PILOT', 10);
        $q->pauseAdmission($ctx, 'SYNTHETIC_U93_PILOT_PAUSE');
        $v = (new MockUncertaintyReviewBoard($ctx, $q))->overview($ctx);
        check($v['admission']['state'] === 'PAUSED'
            && $v['queue']['pending'] === 1
            && $v['real_authorization'] === false
            && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0
            && $mut->submitCount() === 0
            && $coord->events() === []);
    },
];
