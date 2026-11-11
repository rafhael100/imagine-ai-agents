<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\MockReadOnlyRunQueue;

return [
    'U101-01 empty queue capacity is closed and NO_GO' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $v = $q->capacityOverview(context());
        check(array_keys($v) === [
            'contract', 'alert', 'jobs', 'audit', 'decisions',
            'resume_budget_available', 'admission', 'operational_decision',
            'real_authorization', 'external_calls', 'write_calls',
        ]);
        check($v['contract'] === 'SYNTHETIC_QUEUE_CAPACITY_V1'
            && $v['alert'] === 'NORMAL'
            && $v['jobs'] === ['used' => 0, 'limit' => 64, 'remaining' => 64]
            && $v['audit'] === ['used' => 0, 'limit' => 256, 'remaining' => 256]
            && $v['decisions'] === ['used' => 0, 'limit' => 32, 'remaining' => 32]
            && $v['resume_budget_available'] === false
            && $v['admission'] === ['state' => 'OPEN']
            && $v['operational_decision'] === 'NO_GO'
            && $v['real_authorization'] === false
            && $v['external_calls'] === 0 && $v['write_calls'] === 0
            && $q->events() === []);
    },
    'U101-02 queued job consumes one slot and one audit entry' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U101_A', 'SYNTHETIC_U101_REQ_A', 10);
        $v = $q->capacityOverview(context());
        check($v['jobs']['used'] === 1 && $v['jobs']['remaining'] === 63
            && $v['audit']['used'] === 1 && $v['alert'] === 'NORMAL');
    },
    'U101-03 reservation changes audit but not allocated job slots' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U101_B', 'SYNTHETIC_U101_REQ_B', 10);
        $q->claim(context(), 11, 5);
        $v = $q->capacityOverview(context());
        check($v['jobs']['used'] === 1 && $v['audit']['used'] === 2
            && $v['resume_budget_available'] === false);
    },
    'U101-04 completed job still reserves a historical slot' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $id = 'SYNTHETIC_U101_C';
        $q->enqueue(context(), $id, 'SYNTHETIC_U101_REQ_C', 10);
        $claim = $q->claim(context(), 11, 5);
        $q->finish(context(), $id, $claim['claim_ref'], 'CONCLUIDA', 12);
        $v = $q->capacityOverview(context());
        check($v['jobs']['used'] === 1 && $v['audit']['used'] === 3
            && $v['jobs']['remaining'] === 63);
    },
    'U101-05 cancelled job does not free a historical slot' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $id = 'SYNTHETIC_U101_D';
        $q->enqueue(context(), $id, 'SYNTHETIC_U101_REQ_D', 10);
        $q->cancel(context(), $id, 11);
        $v = $q->capacityOverview(context());
        check($v['jobs']['used'] === 1 && $v['audit']['used'] === 2);
    },
    'U101-06 unknown job remains counted after lease expiration' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U101_E', 'SYNTHETIC_U101_REQ_E', 10);
        $q->claim(context(), 11, 2);
        $q->claim(context(), 13, 5);
        $v = $q->capacityOverview(context());
        check($v['jobs']['used'] === 1 && $v['audit']['used'] === 3
            && $q->counts()['INDETERMINADO'] === 1);
    },
    'U101-07 paused queue reports bounded resume budget but no permission' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->pauseAdmission(context(), 'SYNTHETIC_U101_PAUSE_F');
        $v = $q->capacityOverview(context());
        check($v['admission'] === ['state' => 'PAUSED']
            && $v['decisions']['used'] === 1
            && $v['audit']['used'] === 1
            && $v['resume_budget_available'] === true
            && $v['operational_decision'] === 'NO_GO');
    },
    'U101-08 resumed state closes resume-budget observation' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->pauseAdmission(context(), 'SYNTHETIC_U101_PAUSE_G');
        $q->resumeAdmission(context(), 'SYNTHETIC_U101_RESUME_G');
        $v = $q->capacityOverview(context());
        check($v['admission'] === ['state' => 'OPEN']
            && $v['decisions']['used'] === 2
            && $v['resume_budget_available'] === false);
    },
    'U101-09 other tenant cannot see capacity or admission' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        denied('CONTEXT_DENIED', fn () => $q->capacityOverview(context('SYNTHETIC_B')));
        check($q->capacityOverview(context())['jobs']['used'] === 0);
    },
    'U101-10 full 64-job queue is visibly exhausted without eviction' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        for ($i = 0; $i < 64; ++$i) {
            $q->enqueue(context(), 'SYNTHETIC_U101_JOB_' . $i,
                'SYNTHETIC_U101_REQ_' . $i, 10);
        }
        $v = $q->capacityOverview(context());
        check($v['alert'] === 'EXHAUSTED'
            && $v['jobs']['remaining'] === 0
            && $v['audit']['used'] === 64);
        denied('QUEUE_CAPACITY_DENIED', fn () => $q->enqueue(context(),
            'SYNTHETIC_U101_JOB_OVER', 'SYNTHETIC_U101_REQ_OVER', 10));
        check($q->capacityOverview(context()) === $v);
    },
    'U101-11 four remaining job slots trigger near-limit warning' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        for ($i = 0; $i < 60; ++$i) {
            $q->enqueue(context(), 'SYNTHETIC_U101_NEAR_' . $i,
                'SYNTHETIC_U101_NEAR_REQ_' . $i, 10);
        }
        $v = $q->capacityOverview(context());
        check($v['alert'] === 'NEAR_LIMIT'
            && $v['jobs']['used'] === 60 && $v['jobs']['remaining'] === 4);
    },
    'U101-12 emergency pause slot is not enough to resume' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        for ($i = 0; $i < 15; ++$i) {
            $q->pauseAdmission(context(), 'SYNTHETIC_U101_P_' . $i);
            $q->resumeAdmission(context(), 'SYNTHETIC_U101_R_' . $i);
        }
        $q->pauseAdmission(context(), 'SYNTHETIC_U101_P_LAST');
        $v = $q->capacityOverview(context());
        check($v['alert'] === 'NEAR_LIMIT'
            && $v['decisions']['used'] === 31
            && $v['decisions']['remaining'] === 1
            && $v['resume_budget_available'] === false
            && $v['admission']['state'] === 'PAUSED');
        denied('QUEUE_CONTROL_CAPACITY_DENIED', fn () => $q->resumeAdmission(
            context(), 'SYNTHETIC_U101_R_LAST'));
        check($q->capacityOverview(context()) === $v);
    },
    'U101-13 repeated observation does not mutate state or audit' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U101_F', 'SYNTHETIC_U101_REQ_F', 10);
        $events = $q->events();
        $before = $q->capacityOverview(context());
        for ($i = 0; $i < 100; ++$i) {
            check($q->capacityOverview(context()) === $before);
        }
        check($q->events() === $events);
    },
    'U101-14 capacity report contains no raw tenant, decision or run ID' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U101_PRIVATE',
            'SYNTHETIC_U101_REQ_PRIVATE', 10);
        $q->pauseAdmission(context(), 'SYNTHETIC_U101_PRIVATE_PAUSE');
        $v = $q->capacityOverview(context());
        $json = json_encode($v, JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC_U101_PRIVATE', 'SYNTHETIC_U101_REQ_PRIVATE',
            'SYNTHETIC_U101_PRIVATE_PAUSE', context()->tenant,
            'run_ref', 'claim_ref', 'request_ref', 'nonce'] as $marker) {
            check(!str_contains($json, $marker));
        }
        check($v['real_authorization'] === false
            && $v['external_calls'] === 0 && $v['write_calls'] === 0);
    },
];
