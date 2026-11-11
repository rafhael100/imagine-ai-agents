<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\{MockReadOnlyRunQueue, MockUncertaintyReviewBoard};

function f187OverviewFixture(): array
{
    $ctx = context(); $q = new MockReadOnlyRunQueue($ctx);
    for ($i = 1; $i <= 3; ++$i) {
        $q->enqueue($ctx, 'SYNTHETIC_U87_RUN_' . $i,
            'SYNTHETIC_U87_REQ_' . $i, 10);
    }
    for ($i = 1; $i <= 3; ++$i) $q->claim($ctx, 11, 2);
    $q->claim($ctx, 13, 5);
    return [new MockUncertaintyReviewBoard($ctx, $q), $q, $ctx];
}

return [
    'U87-01 empty queue produces all-zero redacted supervision report' => function (): void {
        $ctx = context(); $q = new MockReadOnlyRunQueue($ctx);
        $overview = (new MockUncertaintyReviewBoard($ctx, $q))->overview($ctx);
        check($overview['contract'] === 'SYNTHETIC_QUEUE_SUPERVISION_V1'
            && array_sum($overview['queue']) === 0
            && array_sum($overview['review']) === 0
            && $overview['operational_decision'] === 'NO_GO'
            && $overview['real_authorization'] === false
            && $overview['external_calls'] === 0 && $overview['write_calls'] === 0);
    },
    'U87-02 indeterminate job without votes is counted awaiting-first' => function (): void {
        [$board, $q, $ctx] = f187OverviewFixture();
        $view = $board->overview($ctx);
        check($view['queue']['indeterminate'] === 3
            && $view['review']['awaiting_first'] === 3
            && array_sum($view['review']) === 3
            && count($board->events()) === 0);
    },
    'U87-03 first independent vote changes only review tally' => function (): void {
        [$board, $q, $ctx] = f187OverviewFixture();
        $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_1',
            'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
        $v = $board->overview($ctx);
        check($v['review']['awaiting_first'] === 2
            && $v['review']['awaiting_second'] === 1
            && $v['queue']['indeterminate'] === 3
            && $q->counts()['INDETERMINADO'] === 3);
    },
    'U87-04 matching second vote counts as reviewed but unresolved' => function (): void {
        [$board, $q, $ctx] = f187OverviewFixture();
        foreach (['SYNTHETIC_REVIEWER_A', 'SYNTHETIC_REVIEWER_B'] as $who) {
            $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_1',
                $who, 'SYNTHETIC_TIMEOUT_OBSERVED');
        }
        $v = $board->overview($ctx);
        check($v['review']['reviewed_unresolved'] === 1
            && $v['review']['awaiting_first'] === 2
            && $v['queue']['indeterminate'] === 3
            && $q->claim($ctx, 14, 5) === null);
    },
    'U87-05 disagreeing votes yield conflict count without authorizing anything' => function (): void {
        [$board, $q, $ctx] = f187OverviewFixture();
        $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_2',
            'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
        $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_2',
            'SYNTHETIC_REVIEWER_B', 'SYNTHETIC_RESULT_UNKNOWN');
        $v = $board->overview($ctx);
        check($v['review']['conflict'] === 1
            && $v['queue']['indeterminate'] === 3
            && $v['real_authorization'] === false && $v['write_calls'] === 0);
    },
    'U87-06 mixed vote states form a closed total equal to uncertainty count' => function (): void {
        [$board, $q, $ctx] = f187OverviewFixture();
        foreach ([1, 2, 3] as $i) {
            $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_' . $i,
                'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
        }
        $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_1',
            'SYNTHETIC_REVIEWER_B', 'SYNTHETIC_TIMEOUT_OBSERVED');
        $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_2',
            'SYNTHETIC_REVIEWER_B', 'SYNTHETIC_RESULT_UNKNOWN');
        $v = $board->overview($ctx);
        check($v['review'] === [
            'awaiting_first' => 0, 'awaiting_second' => 1,
            'reviewed_unresolved' => 1, 'conflict' => 1,
        ]);
        check(array_sum($v['review']) === $v['queue']['indeterminate']
            && $q->counts()['INDETERMINADO'] === 3);
    },
    'U87-07 replay of same observation never changes overview or audit' => function (): void {
        [$board, , $ctx] = f187OverviewFixture();
        $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_1',
            'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
        $before = $board->overview($ctx);
        $board->acknowledge($ctx, 'SYNTHETIC_U87_RUN_1',
            'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
        check($board->overview($ctx) === $before
            && count($board->events()) === 1);
    },
    'U87-08 wrong tenant cannot read supervision aggregate' => function (): void {
        [$board, , $ctx] = f187OverviewFixture();
        denied('CONTEXT_DENIED', fn () =>
            $board->overview(context('SYNTHETIC_B')));
        check($board->overview($ctx)['queue']['indeterminate'] === 3);
    },
    'U87-09 board wired to another tenant queue denies overview' => function (): void {
        $ctx = context();
        $foreignQueue = new MockReadOnlyRunQueue(context('SYNTHETIC_B'));
        $board = new MockUncertaintyReviewBoard($ctx, $foreignQueue);
        denied('CONTEXT_DENIED', fn () => $board->overview($ctx));
    },
    'U87-10 overview is immutable, aggregate-only, never a second CRM action' => function (): void {
        [$board, $q, $ctx] = f187OverviewFixture();
        $events = $q->events();
        $out = $board->overview($ctx);
        check($out === $board->overview($ctx)
            && $q->events() === $events && $board->events() === []);
        $json = json_encode($out, JSON_THROW_ON_ERROR);
        foreach ([$ctx->resource, $ctx->tenant, $ctx->principal,
            'SYNTHETIC_U87_RUN_', 'SYNTHETIC_REVIEWER_A',
            'claim_ref', 'request_ref', 'nonce'] as $marker) {
            check(!str_contains($json, $marker));
        }
    },
];
