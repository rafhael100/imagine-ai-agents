<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\{
    MockReadOnlyRunQueue, MockUncertaintyReviewBoard, MockQueueHealthSnapshot
};

function f205HealthFixture(): array
{
    $ctx = context();
    $queue = new MockReadOnlyRunQueue($ctx);
    $board = new MockUncertaintyReviewBoard($ctx, $queue);
    return [$ctx, $queue, $board, new MockQueueHealthSnapshot($ctx, $board)];
}

return [
    'U102-01 quiet health is not approval or operational readiness' => function (): void {
        [$ctx, $queue, $board, $health] = f205HealthFixture();
        $v = $health->assess($ctx);
        check(array_keys($v) === [
            'contract', 'classification', 'signals', 'triage', 'capacity',
            'operational_decision', 'real_authorization', 'external_calls',
            'write_calls',
        ]);
        check($v['contract'] === 'SYNTHETIC_QUEUE_HEALTH_V1'
            && $v['classification'] === 'QUIET'
            && $v['signals'] === ['NO_LOCAL_WORK']
            && $v['triage']['state'] === 'QUIET'
            && $v['capacity']['jobs']['used'] === 0
            && $v['operational_decision'] === 'NO_GO'
            && $v['real_authorization'] === false
            && $v['external_calls'] === 0 && $v['write_calls'] === 0
            && $queue->events() === [] && $board->events() === []);
    },
    'U102-02 pending work appears once and remains only observation' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_U102_A', 'SYNTHETIC_U102_RA', 10);
        $v = $health->assess($ctx);
        check($v['classification'] === 'PENDING'
            && $v['signals'] === ['PENDING_WORK']
            && $v['triage']['queue']['pending'] === 1
            && $v['capacity']['jobs']['used'] === 1);
    },
    'U102-03 reservation precedes pending and carries both signals' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        foreach (['A', 'B'] as $k) {
            $queue->enqueue($ctx, 'SYNTHETIC_U102_B' . $k,
                'SYNTHETIC_U102_RB' . $k, 10);
        }
        $queue->claim($ctx, 11, 5);
        $v = $health->assess($ctx);
        check($v['classification'] === 'LEASE_IN_PROGRESS'
            && $v['signals'] === ['LEASE_ACTIVE', 'PENDING_WORK']
            && $v['capacity']['jobs']['used'] === 2
            && $v['triage']['queue']['reserved'] === 1);
    },
    'U102-04 lease expiration prioritizes unknown outcome' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_U102_C', 'SYNTHETIC_U102_RC', 10);
        $queue->claim($ctx, 11, 2);
        $queue->claim($ctx, 13, 5);
        $v = $health->assess($ctx);
        check($v['classification'] === 'REVIEW_REQUIRED'
            && $v['signals'] === ['UNKNOWN_OUTCOME']
            && $v['triage']['review']['awaiting_first'] === 1);
    },
    'U102-05 pause takes priority over queued work' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_U102_D', 'SYNTHETIC_U102_RD', 10);
        $queue->pauseAdmission($ctx, 'SYNTHETIC_U102_PAUSED_D');
        $v = $health->assess($ctx);
        check($v['classification'] === 'PAUSED'
            && $v['signals'] === ['ADMISSION_PAUSED', 'PENDING_WORK']
            && $v['triage']['admission'] === $v['capacity']['admission']);
    },
    'U102-06 paused uncertain state retains simultaneous risks' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_U102_E', 'SYNTHETIC_U102_RE', 10);
        $queue->claim($ctx, 11, 2);
        $queue->pauseAdmission($ctx, 'SYNTHETIC_U102_PAUSED_E');
        $queue->claim($ctx, 13, 5);
        $v = $health->assess($ctx);
        check($v['classification'] === 'PAUSED'
            && $v['signals'] === ['ADMISSION_PAUSED', 'UNKNOWN_OUTCOME']
            && $v['triage']['queue']['indeterminate'] === 1);
    },
    'U102-07 resuming does not resolve unknown result' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_U102_F', 'SYNTHETIC_U102_RF', 10);
        $queue->claim($ctx, 11, 2);
        $queue->pauseAdmission($ctx, 'SYNTHETIC_U102_PF');
        $queue->claim($ctx, 13, 5);
        $queue->resumeAdmission($ctx, 'SYNTHETIC_U102_RESUME_F');
        $v = $health->assess($ctx);
        check($v['classification'] === 'REVIEW_REQUIRED'
            && $v['signals'] === ['UNKNOWN_OUTCOME']
            && $v['triage']['admission']['state'] === 'OPEN');
    },
    'U102-08 near-capacity classification before pending work' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        for ($i = 0; $i < 60; ++$i) {
            $queue->enqueue($ctx, 'SYNTHETIC_U102_N' . $i,
                'SYNTHETIC_U102_RN' . $i, 10);
        }
        $v = $health->assess($ctx);
        check($v['classification'] === 'CAPACITY_NEAR_LIMIT'
            && $v['signals'] === ['CAPACITY_NEAR_LIMIT', 'PENDING_WORK']
            && $v['capacity']['jobs']['remaining'] === 4);
    },
    'U102-09 exhausted capacity never claims readiness or emptiness' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        for ($i = 0; $i < 64; ++$i) {
            $queue->enqueue($ctx, 'SYNTHETIC_U102_X' . $i,
                'SYNTHETIC_U102_RX' . $i, 10);
        }
        $v = $health->assess($ctx);
        check($v['classification'] === 'CAPACITY_EXHAUSTED'
            && $v['signals'] === ['CAPACITY_EXHAUSTED', 'PENDING_WORK']
            && $v['capacity']['jobs']['remaining'] === 0
            && $v['real_authorization'] === false);
    },
    'U102-10 emergency pause precedes exhausted capacity' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        for ($i = 0; $i < 64; ++$i) {
            $queue->enqueue($ctx, 'SYNTHETIC_U102_P' . $i,
                'SYNTHETIC_U102_RP' . $i, 10);
        }
        $queue->pauseAdmission($ctx, 'SYNTHETIC_U102_STOP');
        $v = $health->assess($ctx);
        check($v['classification'] === 'PAUSED'
            && $v['signals'] === [
                'ADMISSION_PAUSED', 'CAPACITY_EXHAUSTED', 'PENDING_WORK',
            ]);
    },
    'U102-11 cross-tenant health inspection is denied' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        denied('CONTEXT_DENIED',
            fn () => $health->assess(context('SYNTHETIC_B')));
        check($queue->events() === []
            && $health->assess($ctx)['classification'] === 'QUIET');
    },
    'U102-12 bound board rejects foreign-tenant queue' => function (): void {
        $ctx = context();
        $foreignQueue = new MockReadOnlyRunQueue(context('SYNTHETIC_B'));
        $board = new MockUncertaintyReviewBoard($ctx, $foreignQueue);
        $health = new MockQueueHealthSnapshot($ctx, $board);
        denied('CONTEXT_DENIED', fn () => $health->assess($ctx));
    },
    'U102-13 report cannot expose raw run, request, tenant or reviewer' => function (): void {
        [$ctx, $queue, , $health] = f205HealthFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_U102_PRIVATE_RUN',
            'SYNTHETIC_U102_PRIVATE_REQUEST', 10);
        $queue->pauseAdmission($ctx, 'SYNTHETIC_U102_PRIVATE_PAUSE');
        $v = $health->assess($ctx);
        $encoded = json_encode($v, JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC_U102_PRIVATE_RUN',
            'SYNTHETIC_U102_PRIVATE_REQUEST',
            'SYNTHETIC_U102_PRIVATE_PAUSE', $ctx->tenant,
            'request_ref', 'run_ref', 'claim_ref', 'reviewer_ref', 'nonce'] as $key) {
            check(!str_contains($encoded, $key));
        }
        check($v['write_calls'] === 0 && $v['external_calls'] === 0);
    },
    'U102-14 repeated supervision does not mutate queue or board' => function (): void {
        [$ctx, $queue, $board, $health] = f205HealthFixture();
        $queue->enqueue($ctx, 'SYNTHETIC_U102_STABLE',
            'SYNTHETIC_U102_STABLE_REQ', 10);
        $events = $queue->events();
        $first = $health->assess($ctx);
        for ($i = 0; $i < 100; ++$i) {
            check($health->assess($ctx) === $first);
        }
        check($queue->events() === $events && $board->events() === []);
    },
];
