<?php
declare(strict_types=1);

namespace ImagineAI\MockOrchestration;

use ImagineAI\Domain\{MockError, TenantContext};

/**
 * Read-only decision support for one synthetic queue. It never invokes a
 * worker, CRM, external provider or state-changing queue method.
 *
 * "next_step" is an observation label, not an operational instruction.
 * Only real, independently reviewed integrations could ever grant access.
 */
final class MockQueueTriage
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MockUncertaintyReviewBoard $board,
    ) {}

    /** Fully redacted report: no Run, claim, request, reviewer or decision IDs. */
    public function assess(TenantContext $context): array
    {
        $this->context->assertSame($context);
        // overview() enforces the queue's actual tenant as well.
        $view = $this->board->overview($context);
        $keys = ['contract', 'queue', 'review', 'admission',
            'operational_decision', 'real_authorization',
            'external_calls', 'write_calls'];
        if (array_keys($view) !== $keys
            || $view['contract'] !== 'SYNTHETIC_QUEUE_SUPERVISION_V1'
            || $view['operational_decision'] !== 'NO_GO'
            || $view['real_authorization'] !== false
            || $view['external_calls'] !== 0
            || $view['write_calls'] !== 0
            || !is_array($view['admission'])
            || array_keys($view['admission']) !== ['state']
            || !in_array($view['admission']['state'], ['OPEN', 'PAUSED'], true)) {
            MockError::fail('TRIAGE_CONTRACT_DENIED');
        }
        $queueKeys = ['pending', 'reserved', 'completed',
            'failed', 'cancelled', 'indeterminate'];
        $reviewKeys = ['awaiting_first', 'awaiting_second',
            'reviewed_unresolved', 'conflict'];
        if (!is_array($view['queue']) || !is_array($view['review'])
            || array_keys($view['queue']) !== $queueKeys
            || array_keys($view['review']) !== $reviewKeys) {
            MockError::fail('TRIAGE_CONTRACT_DENIED');
        }
        foreach ([$view['queue'], $view['review']] as $counts) {
            foreach ($counts as $count) {
                if (!is_int($count) || $count < 0 || $count > 64) {
                    MockError::fail('TRIAGE_COUNTS_DENIED');
                }
            }
        }
        $queue = $view['queue'];
        $review = $view['review'];
        if (array_sum($queue) > 64
            || array_sum($review) !== $queue['indeterminate']) {
            MockError::fail('TRIAGE_COUNTS_DENIED');
        }

        // Higher-risk states take precedence; uncertainty is never cleared
        // by a completed vote and PAUSED never looks like EMPTY.
        if ($view['admission']['state'] === 'PAUSED') {
            $state = 'PAUSED';
            $next = 'INSPECT_PAUSE';
        } elseif ($queue['indeterminate'] > 0) {
            $state = 'REVIEW_REQUIRED';
            $next = 'INSPECT_UNCERTAINTY';
        } elseif ($queue['reserved'] > 0) {
            $state = 'LEASE_IN_PROGRESS';
            $next = 'OBSERVE_LEASE';
        } elseif ($queue['pending'] > 0) {
            $state = 'PENDING';
            $next = 'OBSERVE_PENDING';
        } else {
            $state = 'QUIET';
            $next = 'NO_LOCAL_WORK';
        }
        return [
            'contract' => 'SYNTHETIC_QUEUE_TRIAGE_V1',
            'state' => $state,
            'next_step' => $next,
            'queue' => $queue,
            'review' => $review,
            'admission' => $view['admission'],
            'operational_decision' => 'NO_GO',
            'real_authorization' => false,
            'external_calls' => 0,
            'write_calls' => 0,
        ];
    }
}
