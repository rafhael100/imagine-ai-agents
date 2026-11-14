<?php
declare(strict_types=1);

namespace ImagineAI\MockOrchestration;

use ImagineAI\Domain\{MockError, TenantContext};

/**
 * Local, redacted supervision digest. Combines the EXACT same synthetic queue
 * for triage and capacity, never an operational readiness/GO decision.
 *
 * No network, identity, worker, agent execution or state mutation.
 */
final class MockQueueHealthSnapshot
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MockUncertaintyReviewBoard $board,
    ) {}

    public function assess(TenantContext $context): array
    {
        $this->context->assertSame($context);
        $triage = (new MockQueueTriage($context, $this->board))->assess($context);
        // Fetch from the board's OWN queue; a caller cannot mix two queues.
        $capacity = $this->board->capacityOverview($context);
        $flags = ['operational_decision' => 'NO_GO',
            'real_authorization' => false, 'external_calls' => 0,
            'write_calls' => 0];
        foreach ([$triage, $capacity] as $item) {
            foreach ($flags as $key => $value) {
                if (!array_key_exists($key, $item) || $item[$key] !== $value) {
                    MockError::fail('HEALTH_AUTHORITY_DENIED');
                }
            }
        }
        if ($triage['contract'] !== 'SYNTHETIC_QUEUE_TRIAGE_V1'
            || $capacity['contract'] !== 'SYNTHETIC_QUEUE_CAPACITY_V1'
            || !is_array($triage['queue'])
            || !is_array($triage['review'])
            || !is_array($capacity['jobs'])
            || $capacity['jobs']['limit'] !== 64
            || !is_int($capacity['jobs']['used'])
            || array_sum($triage['queue']) !== $capacity['jobs']['used']
            || $triage['admission'] !== $capacity['admission']
            || !in_array($capacity['alert'],
                ['NORMAL', 'NEAR_LIMIT', 'EXHAUSTED'], true)
            || array_sum($triage['review']) !==
                $triage['queue']['indeterminate']) {
            MockError::fail('HEALTH_INVARIANT_DENIED');
        }

        // Ordered alert signals make simultaneous risks visible.
        // "NO_LOCAL_WORK" is informational, not CRM/production readiness.
        $signals = [];
        if ($triage['admission']['state'] === 'PAUSED') {
            $signals[] = 'ADMISSION_PAUSED';
        }
        if ($triage['queue']['indeterminate'] > 0) {
            $signals[] = 'UNKNOWN_OUTCOME';
        }
        if ($capacity['alert'] === 'EXHAUSTED') {
            $signals[] = 'CAPACITY_EXHAUSTED';
        } elseif ($capacity['alert'] === 'NEAR_LIMIT') {
            $signals[] = 'CAPACITY_NEAR_LIMIT';
        }
        if ($triage['queue']['reserved'] > 0) {
            $signals[] = 'LEASE_ACTIVE';
        }
        if ($triage['queue']['pending'] > 0) {
            $signals[] = 'PENDING_WORK';
        }
        if ($signals === []) $signals[] = 'NO_LOCAL_WORK';

        if ($triage['admission']['state'] === 'PAUSED') {
            $classification = 'PAUSED';
        } elseif ($triage['queue']['indeterminate'] > 0) {
            $classification = 'REVIEW_REQUIRED';
        } elseif ($capacity['alert'] === 'EXHAUSTED') {
            $classification = 'CAPACITY_EXHAUSTED';
        } elseif ($capacity['alert'] === 'NEAR_LIMIT') {
            $classification = 'CAPACITY_NEAR_LIMIT';
        } elseif ($triage['queue']['reserved'] > 0) {
            $classification = 'LEASE_IN_PROGRESS';
        } elseif ($triage['queue']['pending'] > 0) {
            $classification = 'PENDING';
        } else {
            $classification = 'QUIET';
        }
        return [
            'contract' => 'SYNTHETIC_QUEUE_HEALTH_V1',
            'classification' => $classification,
            'signals' => $signals,
            'triage' => $triage,
            'capacity' => $capacity,
            ...$flags,
        ];
    }
}
