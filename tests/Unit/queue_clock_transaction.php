<?php
declare(strict_types=1);

use ImagineAI\MockOrchestration\MockReadOnlyRunQueue;

return [
    'U89-01 late idempotent enqueue cannot poison the queue clock' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $a = $q->enqueue(context(), 'SYNTHETIC_U89_01A', 'SYNTHETIC_U89_REQ_01A', 10);
        check($q->enqueue(context(), 'SYNTHETIC_U89_01A', 'SYNTHETIC_U89_REQ_01A', 1000000) === $a);
        $q->enqueue(context(), 'SYNTHETIC_U89_01B', 'SYNTHETIC_U89_REQ_01B', 11);
        check($q->claim(context(), 12, 5)['run_ref'] === 'SYNTHETIC_U89_01A');
        check(count($q->events()) === 3);
    },
    'U89-02 foreign run reusing request cannot push time on conflict' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_02A', 'SYNTHETIC_U89_REQ_02A', 10);
        denied('QUEUE_REQUEST_CONFLICT', fn () => $q->enqueue(
            context(), 'SYNTHETIC_U89_02B', 'SYNTHETIC_U89_REQ_02A', 1000000));
        $q->enqueue(context(), 'SYNTHETIC_U89_02B', 'SYNTHETIC_U89_REQ_02B', 11);
        check($q->counts()['PENDENTE'] === 2 && count($q->events()) === 2);
    },
    'U89-03 run idempotency conflict must not poison timeline' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_03A', 'SYNTHETIC_U89_REQ_03A', 10);
        denied('QUEUE_IDEMPOTENCY_CONFLICT', fn () => $q->enqueue(
            context(), 'SYNTHETIC_U89_03A', 'SYNTHETIC_U89_REQ_FORGED', 1000000));
        $q->enqueue(context(), 'SYNTHETIC_U89_03B', 'SYNTHETIC_U89_REQ_03B', 11);
        check($q->counts()['PENDENTE'] === 2);
    },
    'U89-04 full-queue refusal cannot prevent earlier legitimate claim' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        for ($i = 0; $i < 64; ++$i) {
            $q->enqueue(context(), 'SYNTHETIC_U89_CAP_RUN_' . $i,
                'SYNTHETIC_U89_CAP_REQ_' . $i, 10);
        }
        denied('QUEUE_CAPACITY_DENIED', fn () => $q->enqueue(
            context(), 'SYNTHETIC_U89_CAP_EXTRA', 'SYNTHETIC_U89_CAP_REQ_NEW', 1000000));
        check($q->claim(context(), 11, 5)['run_ref'] === 'SYNTHETIC_U89_CAP_RUN_0');
        check($q->counts()['RESERVADA'] === 1 && count($q->events()) === 65);
    },
    'U89-05 missing cancel request cannot block the pending FIFO' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_05A', 'SYNTHETIC_U89_REQ_05A', 10);
        denied('QUEUE_NOT_FOUND', fn () => $q->cancel(
            context(), 'SYNTHETIC_U89_05_UNKNOWN', 1000000));
        check($q->claim(context(), 11, 5)['run_ref'] === 'SYNTHETIC_U89_05A');
    },
    'U89-06 cancel denied for reserved job cannot invalidate current lease' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_06A', 'SYNTHETIC_U89_REQ_06A', 10);
        $claim = $q->claim(context(), 11, 5);
        denied('QUEUE_STATE_DENIED', fn () => $q->cancel(
            context(), $claim['run_ref'], 1000000));
        check($q->finish(context(), $claim['run_ref'], $claim['claim_ref'],
            'CONCLUIDA', 12)['state'] === 'CONCLUIDA');
        check($q->counts()['CONCLUIDA'] === 1);
    },
    'U89-07 replay of canceled job is idempotent without advancing time' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_07A', 'SYNTHETIC_U89_REQ_07A', 10);
        $c = $q->cancel(context(), 'SYNTHETIC_U89_07A', 11);
        check($q->cancel(context(), 'SYNTHETIC_U89_07A', 1000000) === $c);
        $q->enqueue(context(), 'SYNTHETIC_U89_07B', 'SYNTHETIC_U89_REQ_07B', 12);
        check($q->counts()['CANCELADA'] === 1 && $q->counts()['PENDENTE'] === 1);
    },
    'U89-08 invalid finish result cannot advance clock before denial' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_08A', 'SYNTHETIC_U89_REQ_08A', 10);
        $claim = $q->claim(context(), 11, 5);
        denied('QUEUE_STATE_DENIED', fn () => $q->finish(context(),
            $claim['run_ref'], $claim['claim_ref'], 'PENDENTE', 1000000));
        check($q->finish(context(), $claim['run_ref'],
            $claim['claim_ref'], 'CONCLUIDA', 12)['state'] === 'CONCLUIDA');
    },
    'U89-09 wrong matching-shape claim cannot poison the clock' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_09A', 'SYNTHETIC_U89_REQ_09A', 10);
        $claim = $q->claim(context(), 11, 5);
        denied('QUEUE_CLAIM_DENIED', fn () => $q->finish(context(),
            $claim['run_ref'], 'SYNTHETIC_' . str_repeat('f', 64), 'CONCLUIDA', 1000000));
        check($q->finish(context(), $claim['run_ref'],
            $claim['claim_ref'], 'CONCLUIDA', 12)['state'] === 'CONCLUIDA');
    },
    'U89-10 replay of completed finish cannot poison the clock' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_10A', 'SYNTHETIC_U89_REQ_10A', 10);
        $claim = $q->claim(context(), 11, 5);
        $done = $q->finish(context(), $claim['run_ref'], $claim['claim_ref'],
            'CONCLUIDA', 12);
        check($q->finish(context(), $claim['run_ref'], $claim['claim_ref'],
            'CONCLUIDA', 1000000) === $done);
        $q->enqueue(context(), 'SYNTHETIC_U89_10B', 'SYNTHETIC_U89_REQ_10B', 13);
        check($q->counts()['CONCLUIDA'] === 1 && $q->counts()['PENDENTE'] === 1);
    },
    'U89-11 empty poll at distant time cannot poison new work' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        check($q->claim(context(), 1000000, 2) === null);
        $q->enqueue(context(), 'SYNTHETIC_U89_11A', 'SYNTHETIC_U89_REQ_11A', 10);
        check($q->claim(context(), 11, 5)['run_ref'] === 'SYNTHETIC_U89_11A');
    },
    'U89-12 active but not expired lease poll cannot poison its finish' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_12A', 'SYNTHETIC_U89_REQ_12A', 10);
        $claim = $q->claim(context(), 11, 300);
        check($q->claim(context(), 200, 2) === null);
        check($q->finish(context(), $claim['run_ref'],
            $claim['claim_ref'], 'CONCLUIDA', 12)['state'] === 'CONCLUIDA');
    },
    'U89-13 expired finish advances clock because it changes state' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_13A', 'SYNTHETIC_U89_REQ_13A', 10);
        $claim = $q->claim(context(), 11, 2);
        denied('QUEUE_CLAIM_EXPIRED', fn () => $q->finish(context(),
            $claim['run_ref'], $claim['claim_ref'], 'CONCLUIDA', 13));
        denied('QUEUE_TIME_DENIED', fn () => $q->enqueue(context(),
            'SYNTHETIC_U89_13B', 'SYNTHETIC_U89_REQ_13B', 12));
        $q->enqueue(context(), 'SYNTHETIC_U89_13B', 'SYNTHETIC_U89_REQ_13B', 13);
        check($q->counts()['INDETERMINADO'] === 1 && $q->counts()['PENDENTE'] === 1);
    },
    'U89-14 lease expiration by claim is a committed clock transition' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_14A', 'SYNTHETIC_U89_REQ_14A', 10);
        $q->claim(context(), 11, 2);
        check($q->claim(context(), 13, 5) === null);
        denied('QUEUE_TIME_DENIED', fn () => $q->enqueue(context(),
            'SYNTHETIC_U89_14B', 'SYNTHETIC_U89_REQ_14B', 12));
        $q->enqueue(context(), 'SYNTHETIC_U89_14B', 'SYNTHETIC_U89_REQ_14B', 13);
        check($q->counts()['INDETERMINADO'] === 1);
    },
    'U89-15 successful cancel finish and enqueue stay monotonic' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_15A', 'SYNTHETIC_U89_REQ_15A', 10);
        $q->cancel(context(), 'SYNTHETIC_U89_15A', 12);
        denied('QUEUE_TIME_DENIED', fn () => $q->enqueue(context(),
            'SYNTHETIC_U89_15B', 'SYNTHETIC_U89_REQ_15B', 11));
        $q->enqueue(context(), 'SYNTHETIC_U89_15B', 'SYNTHETIC_U89_REQ_15B', 13);
        $claim = $q->claim(context(), 14, 5);
        $q->finish(context(), $claim['run_ref'], $claim['claim_ref'], 'CONCLUIDA', 15);
        denied('QUEUE_TIME_DENIED', fn () => $q->enqueue(context(),
            'SYNTHETIC_U89_15C', 'SYNTHETIC_U89_REQ_15C', 14));
        $q->enqueue(context(), 'SYNTHETIC_U89_15C', 'SYNTHETIC_U89_REQ_15C', 16);
        check($q->counts()['CONCLUIDA'] === 1
            && $q->counts()['CANCELADA'] === 1 && $q->counts()['PENDENTE'] === 1);
    },
    'U89-16 invalid tenant input and lease cannot poison trusted timeline' => function (): void {
        $q = new MockReadOnlyRunQueue(context());
        $q->enqueue(context(), 'SYNTHETIC_U89_16A', 'SYNTHETIC_U89_REQ_16A', 10);
        denied('CONTEXT_DENIED', fn () => $q->cancel(
            context('SYNTHETIC_B'), 'SYNTHETIC_U89_16A', 1000000));
        denied('QUEUE_LEASE_DENIED', fn () => $q->claim(context(), 1000000, 0));
        denied('QUEUE_REF_DENIED', fn () => $q->enqueue(context(),
            str_repeat('X', 1024 * 1024), 'SYNTHETIC_U89_REQ_16B', 1000000));
        check($q->claim(context(), 11, 5)['run_ref'] === 'SYNTHETIC_U89_16A');
        check($q->counts()['RESERVADA'] === 1 && count($q->events()) === 2);
    },
];
