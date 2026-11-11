<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\{MockUncertaintyReviewBoard, MockReadOnlyRunQueue};
use ImagineAI\Domain\MockError;

/** Build an uncertain synthetic item without triggering a CRM/provider. */
function f186ReviewFixture(): array
{
    $ctx = context();
    $q = new MockReadOnlyRunQueue($ctx);
    $id = 'SYNTHETIC_U86_UNKNOWN';
    $q->enqueue($ctx, $id, 'SYNTHETIC_U86_REQ_UNKNOWN', 10);
    $q->claim($ctx, 11, 2);
    check($q->claim($ctx, 13, 5) === null);
    check($q->counts()['INDETERMINADO'] === 1);
    return [new MockUncertaintyReviewBoard($ctx, $q), $q, $ctx, $id];
}

return [
    'U86-01 pending or absent job cannot be acknowledged as uncertain' => function (): void {
        $ctx = context(); $q = new MockReadOnlyRunQueue($ctx);
        $q->enqueue($ctx, 'SYNTHETIC_U86_PENDING', 'SYNTHETIC_U86_REQ_PENDING', 10);
        $board = new MockUncertaintyReviewBoard($ctx, $q);
        denied('REVIEW_JOB_DENIED', fn () => $board->acknowledge(
            $ctx, 'SYNTHETIC_U86_PENDING', 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED'));
        denied('REVIEW_JOB_DENIED', fn () => $board->acknowledge(
            $ctx, 'SYNTHETIC_U86_MISSING', 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED'));
        check($board->events() === [] && $q->counts()['PENDENTE'] === 1);
    },
    'U86-02 unknown outcome starts at awaiting review with no secret fields' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        check($board->status($ctx, $id) === [
            'state' => 'AWAITING_REVIEW', 'review_count' => 0,
            'real_authorization' => false]);
        check($q->counts()['INDETERMINADO'] === 1 && $board->events() === []);
    },
    'U86-03 first acknowledgement is insufficient to finalize review' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        $out = $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        check($out['state'] === 'AWAITING_SECOND_REVIEW'
            && $out['review_count'] === 1
            && $out['real_authorization'] === false
            && $q->counts()['INDETERMINADO'] === 1
            && count($board->events()) === 1);
    },
    'U86-04 two distinct matching observations remain unresolved' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $out = $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_B',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        check($out === ['state' => 'REVIEWED_UNRESOLVED',
            'review_count' => 2, 'real_authorization' => false]);
        check($q->counts()['INDETERMINADO'] === 1
            && $q->claim($ctx, 14, 5) === null
            && count($board->events()) === 2);
    },
    'U86-05 disagreeing reviewers produce conflict and no resolution' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $out = $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_B',
            'SYNTHETIC_RESULT_UNKNOWN');
        check($out['state'] === 'REVIEW_CONFLICT'
            && $out['review_count'] === 2
            && $q->counts()['INDETERMINADO'] === 1);
    },
    'U86-06 identical reviewer replay is idempotent without extra audit' => function (): void {
        [$board, , $ctx, $id] = f186ReviewFixture();
        $a = $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $b = $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        check($a === $b && count($board->events()) === 1);
    },
    'U86-07 same reviewer cannot alter its observation on replay' => function (): void {
        [$board, , $ctx, $id] = f186ReviewFixture();
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        denied('REVIEWER_CONFLICT', fn () => $board->acknowledge(
            $ctx, $id, 'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_RESULT_UNKNOWN'));
        check(count($board->events()) === 1
            && $board->status($ctx, $id)['review_count'] === 1);
    },
    'U86-08 third reviewer cannot override a complete synthetic review' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        foreach (['SYNTHETIC_REVIEWER_A', 'SYNTHETIC_REVIEWER_B'] as $who) {
            $board->acknowledge($ctx, $id, $who, 'SYNTHETIC_RESULT_UNKNOWN');
        }
        denied('REVIEW_CAPACITY_DENIED', fn () => $board->acknowledge(
            $ctx, $id, 'SYNTHETIC_REVIEWER_C', 'SYNTHETIC_TIMEOUT_OBSERVED'));
        check(count($board->events()) === 2
            && $board->status($ctx, $id)['state'] === 'REVIEWED_UNRESOLVED'
            && $q->counts()['INDETERMINADO'] === 1);
    },
    'U86-09 foreign tenant is denied without any review audit' => function (): void {
        [$board, $q, , $id] = f186ReviewFixture();
        denied('CONTEXT_DENIED', fn () => $board->acknowledge(
            context('SYNTHETIC_B'), $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED'));
        denied('CONTEXT_DENIED', fn () => $board->status(
            context('SYNTHETIC_B'), $id));
        check($board->events() === [] && $q->counts()['INDETERMINADO'] === 1);
    },
    'U86-10 private and oversized reviewer run or reason fail closed' => function (): void {
        [$board, , $ctx, $id] = f186ReviewFixture();
        denied('REVIEWER_REF_DENIED', fn () => $board->acknowledge(
            $ctx, $id, 'PRIVATE_REVIEWER', 'SYNTHETIC_TIMEOUT_OBSERVED'));
        denied('REVIEWER_REF_DENIED', fn () => $board->acknowledge(
            $ctx, $id, str_repeat('X', 1024 * 1024),
            'SYNTHETIC_TIMEOUT_OBSERVED'));
        denied('REVIEW_JOB_DENIED', fn () => $board->acknowledge(
            $ctx, str_repeat('X', 1024 * 1024), 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED'));
        denied('REVIEW_REASON_DENIED', fn () => $board->acknowledge(
            $ctx, $id, 'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_RESULT_CONFIRMED'));
        check($board->events() === []);
    },
    'U86-11 audit hides run IDs, reviewers, reasons and any CRM data' => function (): void {
        [$board, $q, $ctx, $id] = f186ReviewFixture();
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_A',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $board->acknowledge($ctx, $id, 'SYNTHETIC_REVIEWER_B',
            'SYNTHETIC_TIMEOUT_OBSERVED');
        $visible = json_encode([$board->events(), $board->status($ctx, $id)],
            JSON_THROW_ON_ERROR);
        foreach ([$id, $ctx->resource, $ctx->tenant, $ctx->principal,
            'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_REVIEWER_B',
            'SYNTHETIC_TIMEOUT_OBSERVED', 'claim_ref', 'request_ref'] as $private) {
            check(!str_contains($visible, $private));
        }
        check(count($board->events()) === 2
            && $q->counts()['INDETERMINADO'] === 1);
    },
    'U86-12 provider callback cannot forge a human review on its own queue' => function (): void {
        $q = null; $board = null; $blocked = false;
        [$runner, $q, , , $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$board, &$blocked): void {
                try {
                    $board->acknowledge(context(), 'SYNTHETIC_U86_UNKNOWN',
                        'SYNTHETIC_REVIEWER_A', 'SYNTHETIC_TIMEOUT_OBSERVED');
                } catch (MockError $error) {
                    $blocked = $error->getMessage() === 'PROVIDER_CALLBACK_DENIED';
                }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U86_UNKNOWN', 'SYNTHETIC_U86_REQ_U', 10);
        $q->claim($ctx, 11, 2);
        $q->claim($ctx, 13, 5);
        $q->enqueue($ctx, 'SYNTHETIC_U86_WORK', 'SYNTHETIC_U86_REQ_WORK', 13);
        $board = new MockUncertaintyReviewBoard($ctx, $q);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($blocked && $board->events() === []
            && $board->status($ctx, 'SYNTHETIC_U86_UNKNOWN')['review_count'] === 0
            && $q->counts()['INDETERMINADO'] === 1
            && $q->counts()['FALHOU'] === 1
            && $counts->snapshot()['attempts'] === 1
            && $audit->count() === 1 && $mut->submitCount() === 0);
    },
    'U86-13 max 64 uncertain review cases stay bounded to 128 audit records' => function (): void {
        $ctx = context();
        $q = new MockReadOnlyRunQueue($ctx);
        for ($i = 0; $i < 64; ++$i) {
            $q->enqueue($ctx, 'SYNTHETIC_U86_MASS_' . $i,
                'SYNTHETIC_U86_REQ_MASS_' . $i, 10);
        }
        for ($i = 0; $i < 64; ++$i) {
            check($q->claim($ctx, 11, 2) !== null);
        }
        check($q->claim($ctx, 13, 5) === null);
        check($q->counts()['INDETERMINADO'] === 64);
        $board = new MockUncertaintyReviewBoard($ctx, $q);
        for ($i = 0; $i < 64; ++$i) {
            foreach (['SYNTHETIC_REVIEWER_A', 'SYNTHETIC_REVIEWER_B'] as $who) {
                $board->acknowledge($ctx, 'SYNTHETIC_U86_MASS_' . $i,
                    $who, 'SYNTHETIC_TIMEOUT_OBSERVED');
            }
        }
        check(count($board->events()) === 128
            && $board->status($ctx, 'SYNTHETIC_U86_MASS_63')['state']
                === 'REVIEWED_UNRESOLVED');
        check($q->counts()['INDETERMINADO'] === 64);
    },
];
