<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockEvaluation\{MockEvaluationSuite, MockEvaluationCandidate};
use ImagineAI\MockReview\{MockReviewPacket, MockReviewBoard};

function f110Fixture(array $change = [], ?TenantContext $ctx = null): array
{
    $context = $ctx ?? context();
    $suite = new MockEvaluationSuite('SYNTHETIC_REVIEW_SUITE', 'SYNTHETIC_DATASET_V1',
        'SYNTHETIC_RUBRIC_V1', $context, [
            ['id' => 'SYNTHETIC_TENANT', 'severity' => 'CRITICAL', 'expected' => 'DENY'],
            ['id' => 'SYNTHETIC_REVOKE', 'severity' => 'CRITICAL', 'expected' => 'DENY'],
            ['id' => 'SYNTHETIC_CONFLICT', 'severity' => 'REVIEW', 'expected' => 'CONFLICT'],
        ]);
    $correct = ['SYNTHETIC_TENANT' => 'DENY', 'SYNTHETIC_REVOKE' => 'DENY',
        'SYNTHETIC_CONFLICT' => 'CONFLICT'];
    $baseline = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_V1', $suite, $correct);
    $candidate = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_V2', $suite,
        array_replace($correct, $change));
    $packet = new MockReviewPacket('SYNTHETIC_PACKET_A', $context, $suite,
        $baseline, $candidate, 'SYNTHETIC_AUTHOR', 10, 40);
    return [$packet, new MockReviewBoard(), $context, $suite, $baseline, $candidate];
}
function f110Vote(MockReviewBoard $board, MockReviewPacket $packet, TenantContext $ctx,
    string $role, string $reviewer, string $decision = 'ACKNOWLEDGE', int $now = 15,
    ?string $digest = null): void
{
    $board->vote($packet, $ctx, $digest ?? $packet->digest, $reviewer, $role, $decision, $now);
}

return [
    'U10-01 independent reviewers give only human handoff, never promotion' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        check($board->status($packet, $ctx, 15)['state'] === 'AWAITING_INDEPENDENT_REVIEWS');
        f110Vote($board, $packet, $ctx, 'PRODUCT', 'SYNTHETIC_REVIEWER_A');
        check($board->status($packet, $ctx, 15)['review_count'] === 1);
        f110Vote($board, $packet, $ctx, 'SECURITY', 'SYNTHETIC_REVIEWER_B');
        $status = $board->status($packet, $ctx, 15);
        check($status['state'] === 'READY_FOR_HUMAN_HANDOFF' && $status['review_count'] === 2);
        check($status['promotion_allowed'] === false && $status['real_authorization'] === false);
        check($packet->safeView()['promotion_allowed'] === false);
    },
    'U10-02 one reviewer cannot satisfy both roles' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        f110Vote($board, $packet, $ctx, 'PRODUCT', 'SYNTHETIC_REVIEWER_A');
        denied('REVIEW_SEPARATION_DENIED', fn () => f110Vote($board, $packet, $ctx,
            'SECURITY', 'SYNTHETIC_REVIEWER_A'));
        check($board->status($packet, $ctx, 15)['review_count'] === 1);
    },
    'U10-03 author cannot self review under either role' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        denied('REVIEW_SEPARATION_DENIED', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'SYNTHETIC_AUTHOR'));
        denied('REVIEW_SEPARATION_DENIED', fn () => f110Vote($board, $packet, $ctx,
            'SECURITY', 'SYNTHETIC_AUTHOR'));
    },
    'U10-04 stale evidence digest cannot be accepted' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        denied('REVIEW_STALE', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'SYNTHETIC_REVIEWER_A', 'ACKNOWLEDGE', 15, str_repeat('a', 64)));
        check($board->status($packet, $ctx, 15)['review_count'] === 0);
    },
    'U10-05 cross tenant and principal confusion denied' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        $otherTenant = context('SYNTHETIC_B');
        $otherPrincipal = new TenantContext($ctx->product, $ctx->tenant,
            'SYNTHETIC_OTHER', $ctx->purpose, $ctx->resource);
        foreach ([$otherTenant, $otherPrincipal] as $other) {
            denied('REVIEW_DENIED', fn () => f110Vote($board, $packet, $other,
                'PRODUCT', 'SYNTHETIC_REVIEWER_A'));
            denied('REVIEW_DENIED', fn () => $board->status($packet, $other, 15));
        }
    },
    'U10-06 expired and future packets cannot be voted or read' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        foreach ([9, 40] as $time) {
            denied('REVIEW_DENIED', fn () => f110Vote($board, $packet, $ctx,
                'PRODUCT', 'SYNTHETIC_REVIEWER_A', 'ACKNOWLEDGE', $time));
            denied('REVIEW_DENIED', fn () => $board->status($packet, $ctx, $time));
        }
    },
    'U10-07 revocation blocks late votes and status after earlier approvals' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        f110Vote($board, $packet, $ctx, 'PRODUCT', 'SYNTHETIC_REVIEWER_A');
        $packet->revoke();
        denied('REVIEW_DENIED', fn () => f110Vote($board, $packet, $ctx,
            'SECURITY', 'SYNTHETIC_REVIEWER_B'));
        denied('REVIEW_DENIED', fn () => $board->status($packet, $ctx, 15));
    },
    'U10-08 critical evaluation regression blocks acknowledgement' => function (): void {
        [$packet, $board, $ctx] = f110Fixture(['SYNTHETIC_TENANT' => 'ALLOW']);
        check($board->status($packet, $ctx, 15)['state'] === 'BLOCKED_EVALUATION');
        denied('REVIEW_INELIGIBLE', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'SYNTHETIC_REVIEWER_A'));
    },
    'U10-09 incomplete evaluation cannot receive positive acknowledgement' => function (): void {
        [$packet, $board, $ctx] = f110Fixture(['SYNTHETIC_CONFLICT' => 'NOT_EXECUTED']);
        check($packet->reportGate === 'INCONCLUSIVE');
        denied('REVIEW_INELIGIBLE', fn () => f110Vote($board, $packet, $ctx,
            'SECURITY', 'SYNTHETIC_REVIEWER_B'));
    },
    'U10-10 rejection locks the packet for any later vote' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        f110Vote($board, $packet, $ctx, 'SECURITY', 'SYNTHETIC_REVIEWER_B', 'REJECT');
        check($board->status($packet, $ctx, 15)['state'] === 'REJECTED');
        denied('REVIEW_REJECTED', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'SYNTHETIC_REVIEWER_A'));
    },
    'U10-11 exact replay is idempotent, conflicting replay rejected' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        f110Vote($board, $packet, $ctx, 'PRODUCT', 'SYNTHETIC_REVIEWER_A');
        f110Vote($board, $packet, $ctx, 'PRODUCT', 'SYNTHETIC_REVIEWER_A');
        check($board->status($packet, $ctx, 15)['review_count'] === 1);
        denied('REVIEW_CONFLICT', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'SYNTHETIC_REVIEWER_B'));
        denied('REVIEW_CONFLICT', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'SYNTHETIC_REVIEWER_A', 'REJECT'));
    },
    'U10-12 invalid reviewer role decision and identity are denied' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        denied('REVIEW_SCHEMA_DENIED', fn () => f110Vote($board, $packet, $ctx,
            'ADMIN', 'SYNTHETIC_REVIEWER_A'));
        denied('REVIEW_SCHEMA_DENIED', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'SYNTHETIC_REVIEWER_A', 'PROMOTE'));
        denied('CATALOG_SCHEMA_DENIED', fn () => f110Vote($board, $packet, $ctx,
            'PRODUCT', 'REAL_REVIEWER'));
    },
    'U10-13 version dataset and observed result changes invalidate the old digest' => function (): void {
        [$packet, $board, $ctx, $suite, $baseline] = f110Fixture();
        $candidate = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_V2',
            $suite, ['SYNTHETIC_TENANT' => 'DENY',
                'SYNTHETIC_REVOKE' => 'DENY', 'SYNTHETIC_CONFLICT' => 'NOT_EXECUTED']);
        $changed = new MockReviewPacket('SYNTHETIC_PACKET_A', $ctx, $suite,
            $baseline, $candidate, 'SYNTHETIC_AUTHOR', 10, 40);
        check($packet->digest !== $changed->digest);
        denied('REVIEW_STALE', fn () => f110Vote($board, $changed, $ctx,
            'PRODUCT', 'SYNTHETIC_REVIEWER_A', 'ACKNOWLEDGE', 15, $packet->digest));
    },
    'U10-14 review evidence contains only closed fields and no effect capability' => function (): void {
        [$packet, $board, $ctx] = f110Fixture();
        $view = $packet->safeView();
        check(array_keys($view) === ['mode', 'ref', 'digest', 'gate', 'suite_digest',
            'candidate_digest', 'promotion_allowed']);
        check($view['mode'] === 'SYNTHETIC_ONLY' && strlen($view['digest']) === 64);
        check(!str_contains(json_encode($view, JSON_THROW_ON_ERROR), 'SYNTHETIC_AUTHOR'));
        $status = $board->status($packet, $ctx, 15);
        check($status['real_authorization'] === false && $status['promotion_allowed'] === false);
    },
];
