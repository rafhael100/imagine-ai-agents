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
