<?php
declare(strict_types=1);

use ImagineAI\Domain\MockError;
use ImagineAI\MockOrchestration\MockReadOnlyRunQueue;
use ImagineAI\MockPilot\MockQueuedBatchRunner;

return [
    'U92-01 initial admission view has no authority or identifiers' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        check($q->admissionState(context()) === [
            'contract' => 'SYNTHETIC_QUEUE_CONTROL_V1',
            'state' => 'OPEN', 'operational_decision' => 'NO_GO',
            'real_authorization' => false, 'external_calls' => 0,
            'write_calls' => 0,
        ]);
        check($q->events() === []);
    },
    'U92-02 pause blocks new enqueue and pending claims without erasure' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U92_A', 'SYNTHETIC_U92_REQ_A', 10);
        $out = $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_A');
        check($out['state'] === 'PAUSED');
        denied('QUEUE_ADMISSION_PAUSED', fn () => $q->enqueue(
            context(), 'SYNTHETIC_U92_B', 'SYNTHETIC_U92_REQ_B', 11));
        check($q->claim(context(), 11, 5) === null);
        check($q->counts()['PENDENTE'] === 1
            && count($q->events()) === 2);
    },
    'U92-03 existing pending job can be cancelled safely under pause' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U92_C', 'SYNTHETIC_U92_REQ_C', 10);
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_C');
        $out = $q->cancel(context(), 'SYNTHETIC_U92_C', 11);
        check($out['state'] === 'CANCELADA'
            && $q->admissionState(context())['state'] === 'PAUSED'
            && $q->counts()['CANCELADA'] === 1);
    },
    'U92-04 matching live claim may finish while queue is paused' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U92_D', 'SYNTHETIC_U92_REQ_D', 10);
        $claim = $q->claim(context(), 11, 20);
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_D');
        $out = $q->finish(context(), $claim['run_ref'], $claim['claim_ref'],
            'CONCLUIDA', 12);
        check($out['state'] === 'CONCLUIDA'
            && $q->counts()['CONCLUIDA'] === 1
            && $q->admissionState(context())['state'] === 'PAUSED');
    },
    'U92-05 paused poll still expires active claim without new reservation' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U92_E', 'SYNTHETIC_U92_REQ_E', 10);
        $q->enqueue(context(), 'SYNTHETIC_U92_F', 'SYNTHETIC_U92_REQ_F', 10);
        $q->claim(context(), 11, 2);
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_E');
        check($q->claim(context(), 13, 5) === null);
        check($q->counts()['INDETERMINADO'] === 1
            && $q->counts()['PENDENTE'] === 1
            && $q->needsReview(context())
            && $q->events()[4]['code'] === 'SYNTHETIC_LEASE_EXPIRED');
        denied('QUEUE_TIME_DENIED', fn () => $q->cancel(context(),
            'SYNTHETIC_U92_F', 12));
    },
    'U92-06 resume restores pending FIFO without requeuing expired jobs' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U92_G', 'SYNTHETIC_U92_REQ_G', 10);
        $q->enqueue(context(), 'SYNTHETIC_U92_H', 'SYNTHETIC_U92_REQ_H', 10);
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_G');
        $q->resumeAdmission(context(), 'SYNTHETIC_U92_RESUME_G');
        check($q->claim(context(), 11, 5)['run_ref'] === 'SYNTHETIC_U92_G');
        check($q->claim(context(), 11, 5)['run_ref'] === 'SYNTHETIC_U92_H');
        check($q->admissionState(context())['state'] === 'OPEN'
            && $q->counts()['RESERVADA'] === 2);
    },
    'U92-07 foreign tenant cannot pause resume or inspect control' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        denied('CONTEXT_DENIED', fn () => $q->pauseAdmission(
            context('SYNTHETIC_B'), 'SYNTHETIC_U92_FOREIGN'));
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_I');
        denied('CONTEXT_DENIED', fn () => $q->resumeAdmission(
            context('SYNTHETIC_B'), 'SYNTHETIC_U92_RESUME_I'));
        denied('CONTEXT_DENIED', fn () => $q->admissionState(
            context('SYNTHETIC_B')));
        check($q->admissionState(context())['state'] === 'PAUSED');
    },
    'U92-08 malformed decision reference never changes control state' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        denied('QUEUE_REF_DENIED', fn () => $q->pauseAdmission(
            context(), 'PRIVATE_CONTROL'));
        denied('QUEUE_REF_DENIED', fn () => $q->pauseAdmission(
            context(), str_repeat('SYNTHETIC_X', 100000)));
        check($q->admissionState(context())['state'] === 'OPEN'
            && $q->events() === []);
    },
    'U92-09 old pause decision replay cannot repause after a resume' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_J');
        $q->resumeAdmission(context(), 'SYNTHETIC_U92_RESUME_J');
        denied('QUEUE_CONTROL_CONFLICT', fn () => $q->pauseAdmission(
            context(), 'SYNTHETIC_U92_PAUSE_J'));
        check($q->admissionState(context())['state'] === 'OPEN'
            && count($q->events()) === 2);
    },
    'U92-10 decision reference cannot change from pause to resume' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_DECISION_K');
        denied('QUEUE_CONTROL_CONFLICT', fn () => $q->resumeAdmission(
            context(), 'SYNTHETIC_U92_DECISION_K'));
        check($q->admissionState(context())['state'] === 'PAUSED');
    },
    'U92-11 exact pause and resume replays do not append audit records' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $a = $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_L');
        check($q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_L') === $a);
        $b = $q->resumeAdmission(context(), 'SYNTHETIC_U92_RESUME_L');
        check($q->resumeAdmission(context(), 'SYNTHETIC_U92_RESUME_L') === $b);
        check(count($q->events()) === 2
            && $q->events()[0]['code'] === 'SYNTHETIC_ADMISSION_PAUSED'
            && $q->events()[1]['code'] === 'SYNTHETIC_ADMISSION_RESUMED');
        $encoded = json_encode($q->events(), JSON_THROW_ON_ERROR);
        check(!str_contains($encoded, 'SYNTHETIC_U92_RESUME_L')
            && !str_contains($encoded, 'SYNTHETIC_U92_PAUSE_L'));
    },
    'U92-12 different new decision cannot pause twice or resume twice' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_PAUSE_M');
        denied('QUEUE_CONTROL_STATE_DENIED', fn () => $q->pauseAdmission(
            context(), 'SYNTHETIC_U92_PAUSE_M2'));
        $q->resumeAdmission(context(), 'SYNTHETIC_U92_RESUME_M');
        denied('QUEUE_CONTROL_STATE_DENIED', fn () => $q->resumeAdmission(
            context(), 'SYNTHETIC_U92_RESUME_M2'));
        check(count($q->events()) === 2);
    },
    'U92-13 emergency reservation prevents reopening after control cap' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        for ($i = 0; $i < 15; ++$i) {
            $q->pauseAdmission(context(), 'SYNTHETIC_U92_CAP_P_' . $i);
            $q->resumeAdmission(context(), 'SYNTHETIC_U92_CAP_R_' . $i);
        }
        $q->pauseAdmission(context(), 'SYNTHETIC_U92_CAP_P_LAST');
        denied('QUEUE_CONTROL_CAPACITY_DENIED', fn () => $q->resumeAdmission(
            context(), 'SYNTHETIC_U92_CAP_R_LAST'));
        check($q->admissionState(context())['state'] === 'PAUSED'
            && count($q->events()) === 31
            && $q->claim(context(), 500, 2) === null);
    },
    'U92-14 queued pilot reports PAUSED rather than EMPTY and reads nothing' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U92_PILOT', 'SYNTHETIC_U92_REQ_PILOT', 10);
        $q->pauseAdmission($ctx, 'SYNTHETIC_U92_PAUSE_PILOT');
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f183QueuedState('PAUSED'));
        check($q->counts()['PENDENTE'] === 1
            && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0
            && $coord->events() === [] && $mut->submitCount() === 0);
    },
    'U92-15 batch stops on PAUSED without consuming another proof pair' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U92_BATCH', 'SYNTHETIC_U92_REQ_BATCH', 10);
        $q->pauseAdmission($ctx, 'SYNTHETIC_U92_PAUSE_BATCH');
        $batch = new MockQueuedBatchRunner($ctx, $runner);
        $out = $batch->drain($ctx, [
            ['envelope' => $env, 'owner' => $owner],
            ['envelope' => $env, 'owner' => $owner],
        ], ['ref'], 15, 5);
        check($out['state'] === 'PAUSED'
            && $out['processed'] === 0
            && $out['succeeded'] === 0 && $out['denied'] === 0
            && $q->counts()['PENDENTE'] === 1
            && $audit->count() === 0
            && $counts->snapshot()['attempts'] === 0);
    },
    'U92-16 review required takes precedence over pause after lease expiry' => function (): void {
        [$runner, $q, $mut, $coord, $counts, $audit, $env, $owner, $ctx] =
            f179QueuedFixture();
        $q->enqueue($ctx, 'SYNTHETIC_U92_UNKNOWN', 'SYNTHETIC_U92_REQ_UNKNOWN', 10);
        $q->claim($ctx, 11, 2);
        $q->pauseAdmission($ctx, 'SYNTHETIC_U92_PAUSE_UNKNOWN');
        check($runner->runNext($ctx, $env, $owner, ['ref'], 13, 5)
            === f183QueuedState('REVIEW_REQUIRED'));
        check($q->counts()['INDETERMINADO'] === 1
            && $q->admissionState($ctx)['state'] === 'PAUSED'
            && $audit->count() === 0 && $mut->submitCount() === 0);
    },
    'U92-17 provider cannot pause bound queue during a callback' => function (): void {
        $q = null; $blocked = '';
        [$runner, $q, $pilot, $coord, $mut, $counts, $audit, $env, $owner, $ctx] =
            f182QueuedFixture(function () use (&$q, &$blocked): void {
                try { $q->pauseAdmission(context(), 'SYNTHETIC_U92_CALLBACK_PAUSE'); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        $q->enqueue($ctx, 'SYNTHETIC_U92_CALLBACK', 'SYNTHETIC_U92_REQ_CALLBACK', 10);
        check($runner->runNext($ctx, $env, $owner, ['ref'], 15, 5)
            === f182Denial());
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $q->admissionState($ctx)['state'] === 'OPEN'
            && $mut->submitCount() === 0
            && $q->counts()['FALHOU'] === 1);
    },
];
