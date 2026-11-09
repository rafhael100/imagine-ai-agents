<?php
declare(strict_types=1);

namespace ImagineAI\MockReview;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;
use ImagineAI\MockEvaluation\{MockEvaluationSuite, MockEvaluationCandidate, MockEvaluationComparator};

/** Recomputed, immutable fake evaluation evidence. No signatures or real privileges. */
final class MockReviewPacket
{
    private bool $revoked = false;
    public readonly string $digest;
    public readonly string $reportGate;
    public readonly string $suiteDigest;
    public readonly string $baselineDigest;
    public readonly string $candidateDigest;

    public function __construct(
        public readonly string $ref,
        public readonly TenantContext $context,
        MockEvaluationSuite $suite,
        MockEvaluationCandidate $baseline,
        MockEvaluationCandidate $candidate,
        public readonly string $authorRef,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
    ) {
        MockAgentVersionSnapshot::syntheticRef($ref);
        MockAgentVersionSnapshot::syntheticRef($authorRef);
        if ($issuedAt < 0 || $expiresAt <= $issuedAt) {
            MockError::fail('REVIEW_SCHEMA_DENIED');
        }
        // Never accept a caller-supplied gate, score or digest as evidence.
        $report = (new MockEvaluationComparator())->compare($suite, $baseline, $candidate, $context);
        $this->reportGate = $report['gate'];
        $this->suiteDigest = $suite->digest;
        $this->baselineDigest = $baseline->digest;
        $this->candidateDigest = $candidate->digest;
        $this->digest = hash('sha256', json_encode([
            'ref' => $ref, 'context' => $context->key(), 'author' => $authorRef,
            'issued_at' => $issuedAt, 'expires_at' => $expiresAt,
            'report' => $report,
        ], JSON_THROW_ON_ERROR));
    }

    public function revoke(): void { $this->revoked = true; }

    public function assertValid(TenantContext $context, int $now): void
    {
        if ($this->revoked || $this->context->key() !== $context->key()
            || $now < $this->issuedAt || $now >= $this->expiresAt) {
            MockError::fail('REVIEW_DENIED');
        }
    }

    public function safeView(): array
    {
        return [
            'mode' => 'SYNTHETIC_ONLY', 'ref' => $this->ref,
            'digest' => $this->digest, 'gate' => $this->reportGate,
            'suite_digest' => $this->suiteDigest,
            'candidate_digest' => $this->candidateDigest,
            'promotion_allowed' => false,
        ];
    }
}
