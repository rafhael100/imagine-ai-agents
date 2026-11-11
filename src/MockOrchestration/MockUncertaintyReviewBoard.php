<?php
declare(strict_types=1);

namespace ImagineAI\MockOrchestration;

use ImagineAI\Domain\{MockError, TenantContext};

/**
 * Bounded, synthetic two-reviewer notebook for uncertain read-only queue
 * outcomes. Two matching observations do not resolve the outcome, authorize
 * a read, requeue a Run, nor allow a business effect. Pure in-memory mock.
 */
final class MockUncertaintyReviewBoard
{
    private const MAX_CASES = 64;
    private const MAX_EVENTS = 128;
    private const REASONS = [
        'SYNTHETIC_TIMEOUT_OBSERVED', 'SYNTHETIC_RESULT_UNKNOWN',
    ];
    /** @var array<string,array<string,string>> */
    private array $votes = [];
    private array $events = [];

    public function __construct(
        private readonly TenantContext $context,
        private readonly MockReadOnlyRunQueue $queue,
    ) {}

    /** Record one synthetic independent observation, not approval. */
    public function acknowledge(TenantContext $context, string $runRef,
        string $reviewerRef, string $reason): array
    {
        // A provider that has a reference to this board must not alter
        // review/audit state in its own callback. The queue is bound to
        // the corresponding coordinator when used by a queued pilot.
        $this->queue->assertReviewAdmissionReady();
        $this->context->assertSame($context);
        self::assertReviewer($reviewerRef);
        self::assertRunRef($runRef);
        if (!in_array($reason, self::REASONS, true)) {
            MockError::fail('REVIEW_REASON_DENIED');
        }
        if (($this->queue->status($context, $runRef)['state'] ?? null)
            !== 'INDETERMINADO') {
            MockError::fail('REVIEW_JOB_DENIED');
        }

        $votes = $this->votes[$runRef] ?? [];
        if (isset($votes[$reviewerRef])) {
            if ($votes[$reviewerRef] !== $reason) {
                MockError::fail('REVIEWER_CONFLICT');
            }
            return self::view($votes);
        }
        if (count($votes) >= 2) MockError::fail('REVIEW_CAPACITY_DENIED');
        if (!isset($this->votes[$runRef]) && count($this->votes) >= self::MAX_CASES) {
            MockError::fail('REVIEW_CAPACITY_DENIED');
        }
        if (count($this->events) >= self::MAX_EVENTS) {
            MockError::fail('REVIEW_AUDIT_CAPACITY_DENIED');
        }
        $votes[$reviewerRef] = $reason;
        $this->votes[$runRef] = $votes;
        $this->events[] = [
            'sequence' => count($this->events) + 1,
            'event' => 'SYNTHETIC_REVIEW_ACKNOWLEDGED',
            'run_digest' => 'SYNTHETIC_' . hash('sha256', $runRef),
            'reviewer_digest' => 'SYNTHETIC_' . hash('sha256', $reviewerRef),
        ];
        return self::view($votes);
    }

    /** No raw Run/reference, reviewer name or reason appears in this view. */
    public function status(TenantContext $context, string $runRef): array
    {
        $this->context->assertSame($context);
        self::assertRunRef($runRef);
        if (($this->queue->status($context, $runRef)['state'] ?? null)
            !== 'INDETERMINADO') {
            return ['state' => 'DENIED_OR_NOT_FOUND', 'review_count' => 0,
                'real_authorization' => false];
        }
        return self::view($this->votes[$runRef] ?? []);
    }

    /**
     * Read-only, closed aggregate contract for future administrative UIs.
     * No run IDs, claims, reviewers or individual reasons escape.
     * This neither changes queue state nor confirms an unknown outcome.
     */
    public function overview(TenantContext $context): array
    {
        $this->context->assertSame($context);
        // Also check queue ownership (the board and queue could have been
        // wired to different synthetic tenants by a faulty caller).
        $this->queue->needsReview($context);
        $counts = $this->queue->counts();
        $reviewed = [
            'awaiting_first' => 0,
            'awaiting_second' => 0,
            'reviewed_unresolved' => 0,
            'conflict' => 0,
        ];
        $withVotes = 0;
        foreach ($this->votes as $runRef => $votes) {
            if (($this->queue->status($context, $runRef)['state'] ?? null)
                !== 'INDETERMINADO') {
                MockError::fail('REVIEW_INVARIANT_DENIED');
            }
            ++$withVotes;
            $state = self::view($votes)['state'];
            if ($state === 'AWAITING_SECOND_REVIEW') ++$reviewed['awaiting_second'];
            elseif ($state === 'REVIEWED_UNRESOLVED') ++$reviewed['reviewed_unresolved'];
            elseif ($state === 'REVIEW_CONFLICT') ++$reviewed['conflict'];
            else MockError::fail('REVIEW_INVARIANT_DENIED');
        }
        if ($withVotes > $counts['INDETERMINADO']) {
            MockError::fail('REVIEW_INVARIANT_DENIED');
        }
        $reviewed['awaiting_first'] = $counts['INDETERMINADO'] - $withVotes;
        return [
            'contract' => 'SYNTHETIC_QUEUE_SUPERVISION_V1',
            'queue' => [
                'pending' => $counts['PENDENTE'],
                'reserved' => $counts['RESERVADA'],
                'completed' => $counts['CONCLUIDA'],
                'failed' => $counts['FALHOU'],
                'cancelled' => $counts['CANCELADA'],
                'indeterminate' => $counts['INDETERMINADO'],
            ],
            'review' => $reviewed,
            // A paused scheduler is not an empty one. Surface only its
            // closed, redacted state; never a control decision reference.
            'admission' => [
                'state' => $this->queue->admissionState($context)['state'],
            ],
            'operational_decision' => 'NO_GO',
            'real_authorization' => false,
            'external_calls' => 0,
            'write_calls' => 0,
        ];
    }

    /**
     * Same bound queue as overview(), no separate client-supplied queue.
     * No writes, no control decisions, no Run/reviewer identifiers.
     */
    public function capacityOverview(TenantContext $context): array
    {
        $this->context->assertSame($context);
        return $this->queue->capacityOverview($context);
    }

    public function events(): array { return $this->events; }

    private static function view(array $votes): array
    {
        $n = count($votes);
        $state = 'AWAITING_REVIEW';
        if ($n === 1) $state = 'AWAITING_SECOND_REVIEW';
        if ($n === 2) {
            // Even consensus means "we observed uncertainty", not "done".
            $state = count(array_unique(array_values($votes))) === 1
                ? 'REVIEWED_UNRESOLVED' : 'REVIEW_CONFLICT';
        }
        return ['state' => $state, 'review_count' => $n,
            'real_authorization' => false];
    }

    private static function assertReviewer(string $value): void
    {
        if (strlen($value) > 40
            || preg_match('/^SYNTHETIC_REVIEWER_[A-Z]{1,12}$/D', $value) !== 1) {
            MockError::fail('REVIEWER_REF_DENIED');
        }
    }

    private static function assertRunRef(string $value): void
    {
        if (strlen($value) > 80
            || preg_match('/^SYNTHETIC_[A-Z0-9_]{1,70}$/D', $value) !== 1) {
            MockError::fail('REVIEW_JOB_DENIED');
        }
    }
}
